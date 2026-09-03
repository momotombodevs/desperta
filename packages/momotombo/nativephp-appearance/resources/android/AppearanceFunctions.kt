package dev.momotombo.plugins.nativephp_appearance

import android.app.UiModeManager
import android.content.Context
import android.os.Build
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse

object AppearanceFunctions {

    class Set(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val mode = parameters["mode"] as? String
                ?: return BridgeResponse.error("invalid_mode", "An appearance mode is required.")

            val nightMode = when (mode) {
                "system" -> UiModeManager.MODE_NIGHT_AUTO
                "light" -> UiModeManager.MODE_NIGHT_NO
                "dark" -> UiModeManager.MODE_NIGHT_YES
                else -> return BridgeResponse.error("invalid_mode", "Appearance must be system, light, or dark.")
            }

            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                activity.getSystemService(UiModeManager::class.java).setApplicationNightMode(nightMode)
            }

            activity.getSharedPreferences("nativephp_appearance", Context.MODE_PRIVATE)
                .edit()
                .putString("mode", mode)
                .apply()

            return BridgeResponse.success(mapOf("mode" to mode))
        }
    }

    class Get(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val mode = context.getSharedPreferences("nativephp_appearance", Context.MODE_PRIVATE)
                .getString("mode", "system") ?: "system"

            return BridgeResponse.success(mapOf("mode" to mode))
        }
    }
}
