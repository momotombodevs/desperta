package com.momotombo.plugins.nativephp_alarms

import android.app.AlarmManager
import android.app.KeyguardManager
import android.app.Service
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.Manifest
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.media.AudioAttributes
import android.media.MediaPlayer
import android.media.RingtoneManager
import android.net.Uri
import android.os.Build
import android.os.IBinder
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.provider.Settings
import android.util.Log
import android.view.WindowManager
import androidx.fragment.app.FragmentActivity
import androidx.core.app.NotificationCompat
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.lifecycle.NativePHPLifecycle
import com.nativephp.mobile.ui.MainActivity
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONArray
import org.json.JSONObject
import java.util.Calendar
import java.util.UUID

/**
 * Android implementation of the `Alarms.*` NativePHP bridge contract.
 *
 * The bridge handles device concerns only. Application-domain state is
 * represented by caller-supplied occurrence IDs and reconciled through
 * [OccurrenceJournal].
 */
object AlarmsFunctions {
    private const val NOTIFICATION_AUTHORIZATION_CHANGED = "Momotombo\\NativePHPAlarms\\Events\\NotificationAuthorizationChanged"

    private var pendingNotificationPermissionRequest: PendingNotificationPermissionRequest? = null
    private var notificationPermissionResultObserverRegistered = false

    class Capabilities(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = BridgeResponse.success(
            mapOf(
                "exact" to true,
                "snooze" to true,
                "repeating" to true,
                "system_alarm_ui" to true,
                "volume_control" to false,
            ),
        )
    }

    class AuthorizationStatus(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = BridgeResponse.success(
            mapOf("status" to authorizationStatus(context)),
        )
    }

    class RequestAuthorization(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (canScheduleExactly(activity)) {
                return BridgeResponse.success(mapOf("status" to "authorized", "opened_settings" to false))
            }

            activity.startActivity(
                Intent(
                    Settings.ACTION_REQUEST_SCHEDULE_EXACT_ALARM,
                    Uri.parse("package:${activity.packageName}"),
                ),
            )

            return BridgeResponse.success(mapOf("status" to "not_determined", "opened_settings" to true))
        }
    }

    class FullScreenIntentAuthorizationStatus(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = BridgeResponse.success(
            mapOf("authorized" to canUseFullScreenIntent(context)),
        )
    }

    class RequestFullScreenIntentAuthorization(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (canUseFullScreenIntent(activity)) {
                return BridgeResponse.success(mapOf("authorized" to true, "opened_settings" to false))
            }

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
                activity.startActivity(
                    Intent(
                        Settings.ACTION_MANAGE_APP_USE_FULL_SCREEN_INTENT,
                        Uri.parse("package:${activity.packageName}"),
                    ),
                )
            }

            return BridgeResponse.success(mapOf("authorized" to false, "opened_settings" to true))
        }
    }

    class NotificationAuthorizationStatus(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = BridgeResponse.success(
            mapOf("status" to notificationAuthorizationStatus(context)),
        )
    }

    class RequestNotificationAuthorization(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            if (canPostNotifications(activity)) {
                return BridgeResponse.success(mapOf("status" to "authorized", "requested" to false))
            }

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                val requestId = parameters["requestId"] as? String ?: return BridgeResponse.error(
                    "invalid_notification_permission_request",
                    "A notification permission request id is required.",
                )

                if (pendingNotificationPermissionRequest != null) {
                    return BridgeResponse.error(
                        "notification_permission_request_in_progress",
                        "A notification permission request is already in progress.",
                    )
                }

                pendingNotificationPermissionRequest = PendingNotificationPermissionRequest(activity, requestId)
                observeNotificationPermissionResult()
                activity.requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), NOTIFICATION_REQUEST_CODE)
            }

            return BridgeResponse.success(mapOf("status" to "not_determined", "requested" to true))
        }
    }

    class Active(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val alarmId = AlarmPlaybackService.activeAlarmId(context) ?: return BridgeResponse.success(emptyMap())
            val alarm = TriggeredAlarmStore.get(context, alarmId)?.first ?: return BridgeResponse.success(emptyMap())
            val response = mutableMapOf<String, Any>("id" to alarm.id)

            alarm.occurrenceId()?.let { response["occurrence_id"] = it }
            alarm.scheduledFor()?.let { response["scheduled_for"] = it }

            return BridgeResponse.success(response)
        }
    }

    class Schedule(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = schedule(activity, parameters)
    }

    class Occurrences(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = BridgeResponse.success(
            mapOf("occurrences" to OccurrenceJournal.all(context)),
        )
    }

    class AcknowledgeOccurrences(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val occurrenceIds = parameters["occurrence_ids"] as? List<*> ?: emptyList<Any>()
            OccurrenceJournal.remove(context, occurrenceIds.filterIsInstance<String>())

            return BridgeResponse.success(mapOf("acknowledged" to occurrenceIds.size))
        }
    }

    class Update(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> = schedule(activity, parameters)
    }

    class Complete(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val alarmId = parameters["id"] as? String ?: return invalidId()

            complete(activity, alarmId)

            return BridgeResponse.success(mapOf("completed" to true, "id" to alarmId))
        }
    }

    class Cancel(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val alarmId = parameters["id"] as? String ?: return invalidId()

            cancel(activity, alarmId)

            return BridgeResponse.success(mapOf("cancelled" to true, "id" to alarmId))
        }
    }

    class Snooze(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val alarmId = parameters["id"] as? String ?: return invalidId()
            val minutes = (parameters["minutes"] as? Number)?.toInt() ?: return BridgeResponse.error(
                "invalid_snooze", "A snooze duration is required.",
            )

            if (minutes < 1) {
                return BridgeResponse.error("invalid_snooze", "Snooze duration must be at least one minute.")
            }

            if (! snooze(activity, alarmId, minutes)) {
                return BridgeResponse.error("alarm_not_found", "No active alarm was found.")
            }

            return BridgeResponse.success(mapOf("snoozed" to true, "id" to alarmId, "minutes" to minutes))
        }
    }

    internal fun schedule(context: Context, parameters: Map<String, Any>): Map<String, Any> {
        val payload = AlarmPayload.from(parameters) ?: return invalidAlarm()

        if (!canScheduleExactly(context)) {
            return BridgeResponse.error("exact_alarm_permission_denied", "Exact alarm permission is unavailable.")
        }

        AlarmStore.save(context, payload)
        scheduleNext(context, payload)
        OccurrenceJournal.record(context, payload, "scheduled")

        return BridgeResponse.success(mapOf("scheduled" to true, "id" to payload.id))
    }

    internal fun scheduleNext(context: Context, payload: AlarmPayload) {
        val triggerAt = nextTriggerAt(payload)
        val alarmManager = context.getSystemService(AlarmManager::class.java)
        val pendingIntent = alarmIntent(context, payload.id)

        alarmManager.setAlarmClock(
            AlarmManager.AlarmClockInfo(triggerAt, launchIntent(context, payload)),
            pendingIntent,
        )
    }

    internal fun cancel(context: Context, alarmId: String) {
        TriggeredAlarmStore.get(context, alarmId)?.first?.let { OccurrenceJournal.record(context, it, "cancelled") }
        AlarmPlaybackService.stop(context, alarmId)
        context.getSystemService(AlarmManager::class.java).cancel(alarmIntent(context, alarmId))
        context.getSystemService(AlarmManager::class.java).cancel(snoozeIntent(context, alarmId))
        AlarmStore.remove(context, alarmId)
        SnoozeStore.remove(context, alarmId)
        TriggeredAlarmStore.remove(context, alarmId)
        context.getSystemService(NotificationManager::class.java).cancel(NotificationIds.forAlarm(context, alarmId))
    }

    internal fun complete(context: Context, alarmId: String) {
        TriggeredAlarmStore.get(context, alarmId)?.first?.let { OccurrenceJournal.record(context, it, "completed") }
        AlarmPlaybackService.stop(context, alarmId)
        context.getSystemService(NotificationManager::class.java).cancel(NotificationIds.forAlarm(context, alarmId))
        SnoozeStore.remove(context, alarmId)
        TriggeredAlarmStore.remove(context, alarmId)
    }

    internal fun snooze(context: Context, alarmId: String, minutes: Int): Boolean {
        val alarm = TriggeredAlarmStore.get(context, alarmId)?.first ?: return false
        val snoozeAt = System.currentTimeMillis() + minutes * 60_000L

        AlarmPlaybackService.stop(context, alarmId)
        OccurrenceJournal.record(context, alarm, "snoozed")
        SnoozeStore.save(context, alarmId, alarm, snoozeAt)
        context.getSystemService(AlarmManager::class.java).setAlarmClock(
            AlarmManager.AlarmClockInfo(snoozeAt, launchIntent(context, alarm)),
            snoozeIntent(context, alarmId),
        )

        return true
    }

    internal fun nextTriggerAt(payload: AlarmPayload): Long {
        val now = Calendar.getInstance()
        val candidate = Calendar.getInstance().apply {
            set(Calendar.HOUR_OF_DAY, payload.hour)
            set(Calendar.MINUTE, payload.minute)
            set(Calendar.SECOND, 0)
            set(Calendar.MILLISECOND, 0)
        }

        for (daysAhead in 0..7) {
            if (candidate.after(now) && (payload.weekdays.isEmpty() || payload.weekdays.contains(weekday(candidate)))) {
                return candidate.timeInMillis
            }

            candidate.add(Calendar.DAY_OF_YEAR, 1)
        }

        throw IllegalArgumentException("Alarm weekdays are invalid.")
    }

    private fun authorizationStatus(context: Context): String = if (canScheduleExactly(context)) "authorized" else "not_determined"

    private fun notificationAuthorizationStatus(context: Context): String = if (canPostNotifications(context)) "authorized" else "not_determined"

    private fun canPostNotifications(context: Context): Boolean = Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU || context
        .checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED

    private fun canScheduleExactly(context: Context): Boolean = Build.VERSION.SDK_INT < Build.VERSION_CODES.S || context
        .getSystemService(AlarmManager::class.java)
        .canScheduleExactAlarms()

    private fun canUseFullScreenIntent(context: Context): Boolean = Build.VERSION.SDK_INT < Build.VERSION_CODES.UPSIDE_DOWN_CAKE || context
        .getSystemService(NotificationManager::class.java)
        .canUseFullScreenIntent()

    private fun observeNotificationPermissionResult() {
        if (notificationPermissionResultObserverRegistered) {
            return
        }

        notificationPermissionResultObserverRegistered = true

        NativePHPLifecycle.on(NativePHPLifecycle.Events.ON_PERMISSION_RESULT) { result ->
            if (result["permission"] != Manifest.permission.POST_NOTIFICATIONS
                || result["requestCode"] != NOTIFICATION_REQUEST_CODE
            ) {
                return@on
            }

            val pendingRequest = pendingNotificationPermissionRequest ?: return@on
            pendingNotificationPermissionRequest = null

            NativeActionCoordinator.dispatchEvent(
                pendingRequest.activity,
                NOTIFICATION_AUTHORIZATION_CHANGED,
                JSONObject()
                    .put("granted", result["granted"] as? Boolean ?: false)
                    .put("requestId", pendingRequest.requestId)
                    .toString(),
            )
        }
    }

    private data class PendingNotificationPermissionRequest(
        val activity: FragmentActivity,
        val requestId: String,
    )

    private fun weekday(calendar: Calendar): String = when (calendar.get(Calendar.DAY_OF_WEEK)) {
        Calendar.MONDAY -> "monday"
        Calendar.TUESDAY -> "tuesday"
        Calendar.WEDNESDAY -> "wednesday"
        Calendar.THURSDAY -> "thursday"
        Calendar.FRIDAY -> "friday"
        Calendar.SATURDAY -> "saturday"
        Calendar.SUNDAY -> "sunday"
        else -> throw IllegalArgumentException("Unknown weekday.")
    }

    private fun alarmIntent(context: Context, alarmId: String): PendingIntent = PendingIntent.getBroadcast(
        context,
        alarmId.hashCode(),
        Intent(context, AlarmReceiver::class.java)
            .setData(Uri.parse("nativephp-alarm://$alarmId"))
            .putExtra(AlarmReceiver.ALARM_ID, alarmId),
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
    )

    private fun snoozeIntent(context: Context, alarmId: String): PendingIntent = PendingIntent.getBroadcast(
        context,
        ("$alarmId-snooze").hashCode(),
        Intent(context, AlarmReceiver::class.java)
            .setData(Uri.parse("nativephp-alarm-snooze://$alarmId"))
            .putExtra(AlarmReceiver.ALARM_ID, alarmId)
            .putExtra(AlarmReceiver.IS_SNOOZE, true),
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
    )

    internal fun launchIntent(context: Context, alarm: AlarmPayload): PendingIntent = PendingIntent.getActivity(
        context,
        alarm.id.hashCode(),
        navigationIntent(context, alarm) ?: Intent(context, MainActivity::class.java),
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
    )

    internal fun navigationIntent(context: Context, alarm: AlarmPayload): Intent? = alarm.launchPath()
        ?.let { path ->
            Intent(context, MainActivity::class.java)
                .putExtra("notification_url", path)
                .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        }

    internal fun fullScreenIntent(context: Context, alarmId: String): PendingIntent = PendingIntent.getActivity(
        context,
        alarmId.hashCode(),
        Intent(context, AlarmActivity::class.java)
            .setData(Uri.parse("nativephp-alarm://$alarmId"))
            .putExtra(AlarmReceiver.ALARM_ID, alarmId)
            .addFlags(Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP),
        PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
    )

    private fun invalidId(): Map<String, Any> = BridgeResponse.error("invalid_id", "An alarm id is required.")

    private fun invalidAlarm(): Map<String, Any> = BridgeResponse.error("invalid_alarm", "Alarm time or weekdays are invalid.")

    private const val NOTIFICATION_REQUEST_CODE = 7001
}

/** Receives scheduled and snoozed alarms, advances repetition, and starts foreground playback. */
class AlarmReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val alarmId = intent.getStringExtra(ALARM_ID) ?: return
        val snoozed = intent.getBooleanExtra(IS_SNOOZE, false)
        val alarm = (if (snoozed) SnoozeStore.remove(context, alarmId) else AlarmStore.get(context, alarmId))
            ?: return

        val nextAlarm = if (!snoozed && alarm.weekdays.isNotEmpty()) alarm.withNextOccurrence() else null

        if (!snoozed && alarm.weekdays.isEmpty()) {
            AlarmStore.remove(context, alarmId)
        } else if (!snoozed) {
            AlarmStore.save(context, nextAlarm!!)
            AlarmsFunctions.scheduleNext(context, nextAlarm!!)
            OccurrenceJournal.record(context, nextAlarm, "scheduled")
        }

        TriggeredAlarmStore.save(context, alarm, nextAlarm)
        OccurrenceJournal.record(context, alarm, "triggered")

        AlarmPlaybackService.start(context, alarm.id)

        if (! context.getSystemService(KeyguardManager::class.java).isKeyguardLocked) {
            AlarmsFunctions.navigationIntent(context, alarm)
                ?.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                ?.let(context::startActivity)
        }
    }

    companion object {
        const val ALARM_ID = "momotombo.nativephp.alarms.ALARM_ID"
        const val IS_SNOOZE = "momotombo.nativephp.alarms.IS_SNOOZE"
    }
}

/** Locked-screen handoff that opens the neutral launch path carried by an alarm payload. */
class AlarmActivity : FragmentActivity() {
    override fun onCreate(savedInstanceState: android.os.Bundle?) {
        super.onCreate(savedInstanceState)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true)
            setTurnScreenOn(true)
        } else {
            @Suppress("DEPRECATION")
            window.addFlags(WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED or WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON)
        }

        val alarmId = intent.getStringExtra(AlarmReceiver.ALARM_ID) ?: run {
            finish()

            return
        }
        val triggered = TriggeredAlarmStore.get(this, alarmId)
        val alarm = triggered?.first ?: AlarmStore.get(this, alarmId) ?: run {
            finish()

            return
        }

        AlarmsFunctions.navigationIntent(this, alarm)?.let(::startActivity)
        finish()
    }
}

/** Foreground media-playback service for the device's default looping alarm tone. */
class AlarmPlaybackService : Service() {
    private var player: MediaPlayer? = null
    private var activeAlarmId: String? = null

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val alarmId = intent?.getStringExtra(AlarmReceiver.ALARM_ID) ?: run {
            stopSelf(startId)

            return START_NOT_STICKY
        }

        if (intent.action == ACTION_STOP) {
            if (alarmId == activeAlarmId()) {
                stopPlayback()
                stopForeground(STOP_FOREGROUND_REMOVE)
                stopSelf(startId)
            }

            return START_NOT_STICKY
        }

        val alarm = TriggeredAlarmStore.get(this, alarmId)?.first ?: AlarmStore.get(this, alarmId) ?: run {
            stopSelf(startId)

            return START_NOT_STICKY
        }

        stopPlayback()
        activeAlarmId = alarmId
        playbackPreferences().edit().putString(ACTIVE_ALARM_ID, alarmId).apply()
        startForeground(NotificationIds.forAlarm(this, alarm.id), notificationFor(alarm), foregroundServiceType())
        startPlayback(alarm)

        return START_NOT_STICKY
    }

    override fun onDestroy() {
        stopPlayback()
        super.onDestroy()
    }

    private fun startPlayback(alarm: AlarmPayload) {
        try {
            player = MediaPlayer().apply {
                setAudioAttributes(
                    AudioAttributes.Builder()
                        .setUsage(AudioAttributes.USAGE_ALARM)
                        .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
                        .build(),
                )
                setDataSource(this@AlarmPlaybackService, RingtoneManager.getDefaultUri(RingtoneManager.TYPE_ALARM))
                isLooping = true
                prepare()
                start()
            }

            if (alarm.values["vibration"] as? Boolean == true) {
                vibrate()
            }
        } catch (exception: Exception) {
            Log.e(TAG, "Unable to play the system alarm tone.", exception)
            stopPlayback()
        }
    }

    private fun stopPlayback() {
        player?.run {
            if (isPlaying) {
                stop()
            }

            release()
        }
        player = null
        vibrator().cancel()
        activeAlarmId = null
        playbackPreferences().edit().remove(ACTIVE_ALARM_ID).apply()
    }

    private fun activeAlarmId(): String? = activeAlarmId ?: playbackPreferences().getString(ACTIVE_ALARM_ID, null)

    private fun playbackPreferences() = getSharedPreferences(PREFERENCES_NAME, MODE_PRIVATE)

    private fun vibrate() {
        vibrator().vibrate(VibrationEffect.createWaveform(longArrayOf(0, 700, 500), 0))
    }

    private fun vibrator(): Vibrator = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
        getSystemService(VibratorManager::class.java).defaultVibrator
    } else {
        @Suppress("DEPRECATION")
        getSystemService(VIBRATOR_SERVICE) as Vibrator
    }

    private fun notificationFor(alarm: AlarmPayload) = NotificationCompat.Builder(this, CHANNEL_ID)
        .setSmallIcon(smallIcon())
        .setContentTitle(alarm.values["notification_title"] as? String ?: (alarm.values["label"] as? String)?.takeIf(String::isNotBlank) ?: "Alarm")
        .setContentText(alarm.values["notification_body"] as? String ?: "Alarm is ringing.")
        .setCategory(NotificationCompat.CATEGORY_ALARM)
        .setPriority(NotificationCompat.PRIORITY_MAX)
        .setOngoing(true)
        .setOnlyAlertOnce(true)
        .setContentIntent(AlarmsFunctions.launchIntent(this, alarm))
        .setFullScreenIntent(AlarmsFunctions.fullScreenIntent(this, alarm.id), true)
        .build()

    private fun smallIcon(): Int {
        ensureNotificationChannel()

        return resources.getIdentifier("ic_stat_alarm", "drawable", packageName)
    }

    private fun ensureNotificationChannel() {
        getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel(
                CHANNEL_ID,
                "Ringing alarms",
                NotificationManager.IMPORTANCE_HIGH,
            ).apply {
                description = "Shows the alarm that is currently ringing."
                setSound(null, null)
                enableVibration(false)
            },
        )
    }

    private fun foregroundServiceType(): Int = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
        ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PLAYBACK
    } else {
        0
    }

    companion object {
        private const val ACTION_START = "momotombo.nativephp.alarms.action.START_PLAYBACK"
        private const val ACTION_STOP = "momotombo.nativephp.alarms.action.STOP_PLAYBACK"
        private const val CHANNEL_ID = "momotombo.nativephp.alarms.ringing.v1"
        private const val PREFERENCES_NAME = "momotombo.nativephp.alarms.playback"
        private const val ACTIVE_ALARM_ID = "active_alarm_id"
        private const val TAG = "NativePHPAlarms"

        fun start(context: Context, alarmId: String) {
            context.startForegroundService(
                Intent(context, AlarmPlaybackService::class.java)
                    .setAction(ACTION_START)
                    .putExtra(AlarmReceiver.ALARM_ID, alarmId),
            )
        }

        fun stop(context: Context, alarmId: String) {
            context.startService(
                Intent(context, AlarmPlaybackService::class.java)
                    .setAction(ACTION_STOP)
                    .putExtra(AlarmReceiver.ALARM_ID, alarmId),
            )
        }

        internal fun activeAlarmId(context: Context): String? = context
            .getSharedPreferences(PREFERENCES_NAME, MODE_PRIVATE)
            .getString(ACTIVE_ALARM_ID, null)
    }
}

/** Restores stored exact alarms after Android finishes booting. */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED || !canScheduleExactly(context)) {
            return
        }

        AlarmStore.all(context).values.forEach { AlarmsFunctions.scheduleNext(context, it) }
    }

    private fun canScheduleExactly(context: Context): Boolean = Build.VERSION.SDK_INT < Build.VERSION_CODES.S || context
        .getSystemService(AlarmManager::class.java)
        .canScheduleExactAlarms()
}

internal data class AlarmPayload(
    val id: String,
    val hour: Int,
    val minute: Int,
    val weekdays: List<String>,
    val values: Map<String, Any?>,
) {
    fun toMap(): Map<String, Any> = values.filterValues { it != null }.mapValues { it.value!! }

    fun launchPath(): String? = (values["launch_path"] as? String)?.takeIf { it.startsWith('/') }

    fun occurrenceId(): String? = values["occurrence_id"] as? String

    fun scheduledFor(): String? = values["scheduled_for"] as? String

    fun withNextOccurrence(): AlarmPayload = copy(
        values = values + mapOf(
            "occurrence_id" to UUID.randomUUID().toString(),
            "scheduled_for" to nextTriggerAtIso(),
        ),
    )

    private fun nextTriggerAtIso(): String = java.time.Instant.ofEpochMilli(AlarmsFunctions.nextTriggerAt(this)).toString()

    companion object {
        fun from(parameters: Map<String, Any>): AlarmPayload? {
            val id = parameters["id"] as? String ?: return null
            val hour = (parameters["hour"] as? Number)?.toInt() ?: return null
            val minute = (parameters["minute"] as? Number)?.toInt() ?: return null
            val weekdays = parseWeekdays(parameters["weekdays"]) ?: return null

            if (id.isBlank() || hour !in 0..23 || minute !in 0..59 || weekdays.any { it !in WEEKDAYS }) {
                return null
            }

            return AlarmPayload(id, hour, minute, weekdays, parameters)
        }

        /**
         * The Android bridge parses PHP JSON with JSONObject, so arrays arrive
         * as JSONArray rather than Kotlin List instances. Accept both bridge
         * representations while retaining the strict string-only contract.
         */
        private fun parseWeekdays(value: Any?): List<String>? = when (value) {
            is JSONArray -> buildList {
                for (index in 0 until value.length()) {
                    add(value.opt(index) as? String ?: return null)
                }
            }
            is List<*> -> value.map { it as? String ?: return null }
            else -> null
        }

        fun fromJson(json: String): AlarmPayload? = try {
            val objectValue = JSONObject(json)
            val weekdays = objectValue.optJSONArray("weekdays") ?: JSONArray()
            val values = buildMap<String, Any?> {
                objectValue.keys().forEach { key -> put(key, objectValue.get(key)) }
                put("weekdays", (0 until weekdays.length()).map(weekdays::getString))
            }

            from(values.filterValues { it != null }.mapValues { it.value!! })
        } catch (_: Exception) {
            null
        }

        private val WEEKDAYS = setOf("monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday")
    }
}

private object SnoozeStore {
    private const val PREFERENCES = "nativephp_alarm_snoozes"

    fun save(context: Context, alarmId: String, alarm: AlarmPayload, triggerAt: Long) {
        context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE).edit()
            .putString(alarmId, JSONObject(alarm.toMap()).put("snooze_trigger_at", triggerAt).toString())
            .apply()
    }

    fun remove(context: Context, alarmId: String): AlarmPayload? {
        val preferences = context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)
        val value = preferences.getString(alarmId, null)
        preferences.edit().remove(alarmId).apply()

        return value?.let(AlarmPayload::fromJson)
    }
}

private object TriggeredAlarmStore {
    private const val PREFERENCES = "nativephp_triggered_alarms"

    fun save(context: Context, alarm: AlarmPayload, nextAlarm: AlarmPayload?) {
        context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE).edit()
            .putString(alarm.id, JSONObject().put("alarm", JSONObject(alarm.toMap()))
                .put("next", nextAlarm?.let { JSONObject(it.toMap()) }).toString())
            .apply()
    }

    fun get(context: Context, alarmId: String): Pair<AlarmPayload, AlarmPayload?>? {
        val value = context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE).getString(alarmId, null) ?: return null
        val payload = JSONObject(value)
        val alarm = AlarmPayload.fromJson(payload.getJSONObject("alarm").toString()) ?: return null
        val next = payload.optJSONObject("next")?.let { AlarmPayload.fromJson(it.toString()) }

        return alarm to next
    }

    fun remove(context: Context, alarmId: String) {
        context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE).edit().remove(alarmId).apply()
    }
}

/** Device-local lifecycle journal retained until the application acknowledges occurrence IDs. */
private object OccurrenceJournal {
    private const val PREFERENCES = "nativephp_alarm_occurrences"

    fun record(context: Context, alarm: AlarmPayload, status: String) {
        val occurrenceId = alarm.occurrenceId() ?: return
        val scheduledFor = alarm.scheduledFor() ?: return
        val entry = JSONObject()
            .put("alarm_id", alarm.id)
            .put("occurrence_id", occurrenceId)
            .put("scheduled_for", scheduledFor)
            .put("status", status)
            .put("occurred_at", java.time.Instant.now().toString())

        context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE).edit()
            .putString(occurrenceId, entry.toString())
            .apply()
    }

    fun all(context: Context): List<Map<String, Any>> = context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)
        .all
        .values
        .mapNotNull { value -> (value as? String)?.let(::JSONObject) }
        .map { entry ->
            mapOf(
                "alarm_id" to entry.getString("alarm_id"),
                "occurrence_id" to entry.getString("occurrence_id"),
                "scheduled_for" to entry.getString("scheduled_for"),
                "status" to entry.getString("status"),
                "occurred_at" to entry.getString("occurred_at"),
            )
        }

    fun remove(context: Context, executionIds: List<String>) {
        val editor = context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE).edit()
        executionIds.forEach { executionId -> editor.remove(executionId) }
        editor.apply()
    }
}

/** Allocates stable, app-private notification IDs without using alarm ID hashes. */
private object NotificationIds {
    private const val PREFERENCES = "nativephp_alarm_notification_ids"
    private const val NEXT_ID = "next_id"

    fun forAlarm(context: Context, alarmId: String): Int {
        val preferences = context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)
        val key = "alarm:$alarmId"

        if (preferences.contains(key)) {
            return preferences.getInt(key, 0)
        }

        val notificationId = preferences.getInt(NEXT_ID, 10_000)

        preferences.edit()
            .putInt(key, notificationId)
            .putInt(NEXT_ID, notificationId + 1)
            .apply()

        return notificationId
    }
}

private object AlarmStore {
    private const val PREFERENCES = "nativephp_alarms"
    private const val IDS = "ids"

    fun save(context: Context, alarm: AlarmPayload) {
        val ids = ids(context).apply { add(alarm.id) }
        context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)
            .edit()
            .putString(alarm.id, JSONObject(alarm.toMap()).toString())
            .putString(IDS, JSONArray(ids).toString())
            .apply()
    }

    fun get(context: Context, alarmId: String): AlarmPayload? = context
        .getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)
        .getString(alarmId, null)
        ?.let(AlarmPayload::fromJson)

    fun all(context: Context): Map<String, AlarmPayload> = ids(context)
        .mapNotNull { id -> get(context, id)?.let { id to it } }
        .toMap()

    fun remove(context: Context, alarmId: String) {
        val ids = ids(context).apply { remove(alarmId) }
        context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE)
            .edit()
            .remove(alarmId)
            .putString(IDS, JSONArray(ids).toString())
            .apply()
    }

    private fun ids(context: Context): MutableSet<String> = try {
        val array = JSONArray(context.getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE).getString(IDS, "[]"))

        (0 until array.length()).map(array::getString).toMutableSet()
    } catch (_: Exception) {
        mutableSetOf()
    }
}
