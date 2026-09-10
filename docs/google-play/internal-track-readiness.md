# Google Play internal track readiness — Despertá

Audited artifact: `nativephp/android/app/build/outputs/bundle/release/app-release.aab`  
SHA-256: `271e4c706e526cf702597b98905af8af65c229b4f5d0ae080d71b718506ea878`  
Audit date: 2026-09-10

| Check | Verified evidence | Status for internal track |
| --- | --- | --- |
| Identity and platform | `dev.momotombo.desperta`; `1.0.0`; min SDK 33; target SDK 36; signed AAB | Ready |
| Upload version | Effective manifest has `versionCode 1`; the app is already published and the source environment is prepared with code 2 | **New AAB required**; Play does not accept a reused version code |
| Effective permissions | Internet/network state, vibration, flashlight, exact alarms, full-screen intent, boot, foreground service/media playback, notifications; no flashlight use was found in app code | Required alarm permissions are consistent; review the unused flashlight permission when regenerating the native shell |
| Included SDKs | NativePHP/AndroidX/Compose, Browser, Coil + OkHttp, CameraX, Security Crypto + Tink, Request Inspector WebView, RxJava, Gson, Profile Installer; no Firebase, ads, or analytics SDK detected | Consistent with “no collection/no sharing”; recheck the next AAB |
| Public Data safety | Google Play says no data collected and no data shared; privacy policy now discloses network support and user-opened web requests | Ready |
| Families | Public listing shows commitment to the Families Policy; privacy policy now reflects it, but the selected target age ranges are not public | Verify target audience and included-SDK suitability in Play Console |
| Restricted declarations | Exact alarm and full-screen intent are core alarm functionality; `mediaPlayback` foreground service is present | Verify the exact alarm/full-screen intent and foreground-service forms in Play Console |
| Store website | Repository links the official Google Play listing and uses the Play badge; the currently deployed site still shows “Próximamente en Android” | Deploy the existing `docs/` update |

No `native:run` command was executed during this audit.
