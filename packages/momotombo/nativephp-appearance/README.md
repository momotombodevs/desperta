# NativePHP Appearance

`momotombo/nativephp-appearance` persists and applies the application's light, dark, or system appearance at runtime. It is intentionally a small native bridge: it has two public PHP methods, two native bridge functions, and no plugin events.

## Platforms and requirements

| Platform | Minimum version | Native implementation |
| --- | ---: | --- |
| Android | 31 (Android 12) | `UiModeManager.setApplicationNightMode()` and `SharedPreferences` |
| iOS | 15.0 | `UIWindow.overrideUserInterfaceStyle` and `UserDefaults` |

The selected mode is stored locally under `nativephp_appearance.mode`. It is application-local; it does not read or modify the device-wide system theme.

## Install and register

```bash
composer require momotombo/nativephp-appearance
php artisan native:plugin:register momotombo/nativephp-appearance
php artisan native:plugin:validate
```

Registration makes NativePHP include the bridge functions in the generated native shell. A native implementation change requires a new device build; PHP-only callers can be covered with application tests.

## Public PHP API

Use the facade in application code:

```php
use Momotombo\NativephpAppearance\Facades\Appearance;

Appearance::set('dark');

$mode = Appearance::get(); // 'system', 'light', 'dark', or null outside NativePHP
```

| Class or facade method | Input | Result | Behavior |
| --- | --- | --- |
| `Appearance::set(string $mode)` | `system`, `light`, or `dark` | `void` | Validates the mode, applies it natively, then persists it. Invalid values throw `InvalidArgumentException`. Outside a NativePHP runtime it validates but performs no native call. |
| `Appearance::get()` | — | `?string` | Returns the persisted mode reported by the native bridge. It returns `null` when the PHP process has no `nativephp_call()` bridge. |
| `Momotombo\NativephpAppearance\Appearance` | same methods | service class | Bound as a singleton by `AppearanceServiceProvider`; use it for dependency injection instead of the facade when appropriate. |

`set()` is synchronous with the bridge call. Application code should update its own presentation state after calling it if the current screen needs immediate PHP-side state changes.

## Native bridge contract

| Bridge function | Parameters | Success payload | Error |
| --- | --- | --- | --- |
| `Appearance.Set` | `{ "mode": "system" \| "light" \| "dark" }` | `{ "mode": "..." }` | `invalid_mode` when `mode` is absent or unsupported |
| `Appearance.Get` | `{}` | `{ "mode": "system" \| "light" \| "dark" }` | none emitted by the current implementation |

The native implementations are deliberately symmetric:

- Android applies the mode through `UiModeManager` and stores it in `SharedPreferences`.
- iOS changes every connected window's `overrideUserInterfaceStyle` on the main thread and stores it in `UserDefaults`.
- Both use `system` as the default when no selection has yet been persisted.

## Events, hooks, and non-features

This plugin emits no NativePHP events. In particular, there is no `AppearanceCompleted` event and no `execute()` or `getStatus()` public API.

The `nativephp:appearance:copy-assets` build hook currently has no assets to copy; it exists only as an empty extension point. The plugin has no permissions, native activities, services, receivers, or external dependencies.

## Boundaries

- It controls the app appearance only; it does not implement a design system, persist user preferences to a server, or synchronize between devices.
- It does not infer a mode from the system. `get()` returns the mode previously selected through this plugin, defaulting to `system`.
- It does not provide a JavaScript bridge. Use the PHP facade or injected `Appearance` service from NativePHP components.

## Validation

Validate the manifest after changing registration or native bridge metadata:

```bash
php artisan native:plugin:validate packages/momotombo/nativephp-appearance --no-interaction
```

Platform behavior still needs device or simulator acceptance after changes to the Kotlin or Swift sources.

## License

MIT
