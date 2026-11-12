<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== donmanueldev/nativephp-charts/core rules ===

## donmanueldev/nativephp-charts

Native line, area, bar, scatter, pie, and donut charts for NativePHP Mobile, rendered with Swift Charts on iOS and Jetpack Compose on Android.

### Installation

```bash
composer require donmanueldev/nativephp-charts
php artisan vendor:publish --tag=nativephp-plugins-provider --no-interaction
php artisan native:plugin:register donmanueldev/nativephp-charts --no-interaction
php artisan native:plugin:validate
```

Rebuild the selected native target after installing or updating the package because Swift and Kotlin renderers compile into the app.

### Blade usage

Use the native element inside a `NativeComponent` view. The chart is a leaf element, so give it an explicit height and an accessible description.

<code-snippet name="Interactive multi-series area chart" lang="blade">
<native:area-chart
    class="w-full h-80"
    :series="[
        [
            'id' => 'online',
            'name' => 'En línea',
            'color' => '#0F766E',
            'points' => [
                ['id' => 'online-jan', 'x' => '2026-01-01', 'label' => 'Ene', 'value' => 42000],
                ['id' => 'online-feb', 'x' => '2026-02-01', 'label' => 'Feb', 'value' => 51800],
            ],
        ],
        [
            'id' => 'store',
            'name' => 'Tienda',
            'color' => '#7C3AED',
            'points' => [
                ['id' => 'store-jan', 'x' => '2026-01-01', 'label' => 'Ene', 'value' => 31000],
                ['id' => 'store-feb', 'x' => '2026-02-01', 'label' => 'Feb', 'value' => 38600],
            ],
        ],
    ]"
    area-mode="stacked"
    locale="es-NI"
    :x-axis="['type' => 'date', 'dateFormat' => 'medium', 'timezone' => 'America/Managua']"
    :y-axis="['valueFormat' => 'currency', 'currencyCode' => 'NIO', 'maximumFractionDigits' => 0]"
    :legend="['visible' => true, 'position' => 'bottom']"
    _select="pointSelected"
    a11y-label="Ventas mensuales en córdobas"
    :style="[
        'line' => ['width' => 3, 'interpolation' => 'smooth'],
        'area' => ['opacity' => 0.28],
        'points' => ['size' => 5],
        'axis' => ['font' => 'accent', 'labelCount' => 5],
    ]"
/>
</code-snippet>

### Contract

- `<native:line-chart>`, `<native:area-chart>`, `<native:bar-chart>`, and `<native:scatter-chart>` share the versioned Cartesian contract.
- `<native:pie-chart>` and `<native:donut-chart>` use ordered `segments` with unique `id`, `label`, non-negative finite `value`, and `color`. Donut accepts `inner-radius-ratio` from `0.2` through `0.85`.
- `series` accepts multiple ordered series. Each series needs a unique `id`, `name`, `color`, and ordered numeric `points`.
- Give points stable `id` values when using selection. A point may provide `x` as a category string, finite number, `YYYY-MM-DD` date, or ISO-8601 datetime according to `x-axis.type`.
- Area charts support `area-mode="overlay|stacked"`; bar charts use grouped multi-series bars; scatter charts render independent points without connecting lines.
- `legend` supports automatic visibility plus `top`, `bottom`, `leading`, and `trailing` placement.
- Bind `_select="method"` or call `->onSelect('method')`; parse the callback JSON with `Donmanueldev\NativephpCharts\PointSelection::fromJson()`.
- `locale` accepts BCP-47 tags such as `es-NI` and `en-US`; omit it to use the device locale.
- `x-axis.type` accepts `category`, `number`, `date`, or `datetime`. Date axes also accept `dateFormat` and an IANA `timezone`; use the locale-aware `time` preset for compact datetime labels.
- `y-axis.valueFormat` accepts `number`, `currency`, or `percent`. Currency requires a three-letter `currencyCode`.
- `style` is platform-neutral: Cartesian charts use `points`, `grid`, and `axis`; line/area add `line`, area adds `area` with `opacity` and `gradient`, bar adds `bar` with `radius` and optional `width`, and radial charts use `segment` with `gap`, `cornerRadius`, and `opacity`.
- `begin-at-zero` controls line and bar y domains. Area charts retain zero because it is their fill baseline.
- On bar charts, legacy `show-points` controls axis visibility only when neither the structured axis contract nor `style.axis.visible` provides an explicit value.
- Colors accept `#RGB`, `#RRGGBB`, CSS-alpha `#RRGGBBAA`, `black`, `white`, and `transparent`.
- Axis and legend fonts accept a bundled NativePHP font token or configured alias. Unresolved fonts fall back to the system font.
- Legacy scalar formatting and visibility props remain supported for v0.2 consumers, but new code should use `x-axis`, `y-axis`, `legend`, and `style`.

Keep domain labels and `a11y-label` in the application's language. The renderers localize numeric values and their VoiceOver/TalkBack summaries using `locale` and `value-format`.

=== nativephp/mobile/core rules ===

## NativePHP Mobile

- NativePHP Mobile is a Laravel package for building **fully native** iOS and Android apps with PHP. Screens are
rendered as real SwiftUI (iOS) and Jetpack Compose (Android) UI — driven entirely by PHP via SuperNative components
and EDGE Blade elements. A full PHP runtime runs directly on the device with SQLite — no web server required.
- Documentation: `https://nativephp.com/docs/mobile/4/**`
- IMPORTANT: Always activate the `nativephp-mobile` skill every time you work on any NativePHP functionality.
- New app: `laravel new my-app --using=nativephp/mobile-starter --no-node`. A screen is a `NativeComponent` class,
  a Blade view in `resources/views/native/` (`php artisan native:make Name` creates both) and a `Route::native()`
  line in `routes/mobile.php`.
- Check behaviour with Pest and `Native\Mobile\Testing\Native::test()`. It needs no device and takes seconds, where a
  device build takes minutes. The skill has a worked screen, its test, and where builds put the .app and .apk.

### Native UI First — Always

**Always build screens with native UI: `NativeComponent` classes registered via `Route::native()`, rendering EDGE
elements (`native:column`, `native:text`, `native:button`, …).** This is the way to build NativePHP apps.

- Never scaffold new screens as web views, Blade-over-WebView pages, Livewire components, or Inertia pages.
- The web view (the `native:web-view` element) is a legacy/edge-case escape hatch for embedding web content — never the
  foundation of a screen. If the user asks for a webview-based screen, build it natively with EDGE instead and
  explain why; only fall back to the web view if they explicitly insist.
- If the app contains legacy webview screens, proactively suggest converting them to native UI (see the
  `nativephp-webview-to-native` skill).
- EDGE elements are rendered by the `nativephp/mobile-ui` plugin, even `column` and `text`. It must be installed
  and registered (`php artisan native:plugin:register nativephp/mobile-ui`) or elements disappear from the screen.
  Text fields are `outlined-text-input`, `filled-text-input` and `bare-text-input`; there is no `text-input`.
- Style EDGE elements with Tailwind utility classes via `class="..."` / `:class="..."` only — never inline
  CSS `style="..."` attributes or ad-hoc styling props.
- Compose screens from **nested child components**: any `NativeComponent` under `app/NativeComponents` mounts
  as a tag (`UserCard` → `<native:user-card :user="$u" key="user-{{ $u->id }}" @saved="onSaved" />`) with live
  props, its own persistent state, and `emit()` events bubbling to `@event` tag bindings / `#[On('event')]`
  listeners. Prefer extracting a reusable child component over duplicating Blade across screens; give list
  children a stable domain `key` (never the loop index).
- Use `native:icon` (SF Symbols on iOS, Material Icons on Android) for iconography — never emoji characters in
  UI text, labels, or buttons, unless the user explicitly asks for emojis. Prefer the typed icon enums
  (`App\Icons\Ios`, `App\Icons\Android`, `App\Icons\AndroidOutlined`) bound via the `:ios` / `:android`
  attributes, e.g. `:ios="Ios::Gearshape" :android="Android::Settings"`, importing each enum into the view with
  Blade's use directive first. The enums are generated, not shipped — if `app/Icons/` doesn't exist yet, run
  `php artisan native-ui:generate-icons` first (safe to run yourself).

### Theme Tokens, Font Aliases, and Layouts — the Design System Trio

Every app's visual identity belongs in `config/native-ui.php` (publish with
`php artisan vendor:publish --tag=native-ui-config`), not scattered through the markup. When building or
reviewing screens, enforce all three:

1. **Theme tokens over hardcoded colors.** Define the palette once in the config's `theme` block, then style
   with `bg-theme-*` / `text-theme-*` / `border-theme-*` classes (`bg-theme-surface`, `text-theme-on-surface`,
   `border-theme-outline`). Never sprinkle `bg-[#1E2021]`-style arbitrary values for what is really a theme
   role — they can't be re-skinned and don't get automatic dark-mode pairs. Arbitrary color values are for
   genuine data-driven color (per-category identity colors, map imagery, chart series), and those belong in
   one PHP home (an enum or model method), never inline per view. Two capabilities that prevent hex fallbacks:
   - **The token map is open-ended.** When a design needs a role the shipped set lacks (a success green, an
     `outline-variant`), add it to both `light` and `dark` blocks — `bg-theme-success` works immediately; no
     package change required.
   - **Theme classes take opacity modifiers** just like palette classes: `bg-theme-primary/15` is the correct
     tonal-fill idiom (applies to the dark companion too) — never approximate with a hardcoded alpha hex.
2. **Font aliases over file tokens.** Register semantic aliases in the config's `fonts` array
   (`'headline' => 'ArchivoNarrow-Bold'`, `'mono' => 'JetBrainsMono-Regular'`, `'default' => …` for the
   app-wide font) and write `font="headline"` in views — never `font="ArchivoNarrow-Bold"`. Swapping a
   typeface must be a one-line config change.
3. **Native chrome via composable chrome elements (layouts optional).** Author nav bars, tab bars, fabs, and
   side navs directly in the screen's Blade — `<native:top-bar>` (+ `top-bar-action`), `<native:bottom-nav>`
   (+ `bottom-nav-item`), `<native:fab>`, `<native:bottom-bar>`, `<native:side-nav>`. They hoist onto the real
   NavigationStack/TabView chrome (edge-swipe back, predictive back, large titles, Liquid Glass/Material You),
   and their attributes are Blade expressions over screen state, so badges/subtitles/icons are reactive. A
   `NativeLayout` (attached via `Route::native(...)->layout(...)` or `Route::nativeGroup(...)`) is **optional**
   — reach for one only when many screens share identical chrome (e.g. one tabs layout for a tab section); an
   inline chrome element on a screen always overrides the layout's bar for that slot. Add the `custom`
   attribute to a chrome tag only for designs the system bars genuinely can't express — it renders in-tree as
   an ordinary drawn element. Never hand-roll top bars or bottom navs out of rows and pressables — that
   forfeits native back gestures, safe-area handling, and Liquid Glass/Material You. Chrome colors take theme
   tokens (inline: theme classes / `theme()`-fed attributes; builders: `->activeColor(theme('primary'))`) —
   never pasted hex. Bar icons take the platform enums via `:ios-icon` / `:android-icon` with a plain `icon`
   string as cross-platform fallback; bar fonts take config aliases (`font="mono"` / `->font('mono')`). Only
   screens rendered without any chrome (no layout AND no inline bars) may use `safe-area` classes.

### When a Capability Is Missing

If the app needs native functionality or a UI component that core and `native-ui` don't provide:

1. **Look for an existing plugin first.** Check the plugin marketplace (`https://plugins.nativephp.com`) and the
   official core plugins. (If a marketplace-lookup MCP tool is available in your session, use it.)
2. **If no plugin exists, build a custom plugin** with `php artisan native:plugin:create` — plugins bundle
   Swift/Kotlin bridge functions, events, permissions, and can even ship their own native EDGE components.
3. **Never fall back to the web view to fill a native gap.** A missing capability is a reason to write a plugin,
   not a reason to build a webview screen.

### Installing Plugins — Always Register and Verify

Requiring a plugin with Composer is NOT enough — an installed-but-unregistered plugin does nothing. Every plugin
install must follow all three steps:

1. `composer require vendor/plugin-name`
2. `php artisan vendor:publish --tag=nativephp-plugins-provider` — publishes the app's `NativeServiceProvider`
   (needed once, before the first plugin registration; harmless to re-run)
3. `php artisan native:plugin:register vendor/plugin-name` — adds it to the `NativeServiceProvider`
4. `php artisan native:plugin:list` — verify it shows as registered

Then rebuild with `native:run` (see Build and Run below; native code only compiles in at build time). If
`native:run` warns "The following plugins are installed but not registered", go back to
step 3.

### Database Seeding — Always via Migrations

On-device there is no `db:seed` — NativePHP runs **migrations** on app start (once each, tracked, versioned).
Whenever asked to seed the database, use the migration trick: create a dedicated migration
(`php artisan make:migration seed_app_settings`) and put the inserts in `up()`. If a Seeder class helps organize
the data, still create it — but invoke it **from the migration's `up()`** (e.g. `(new CategorySeeder)->run()`),
never rely on `db:seed` being run. Seed migrations must be safe for both fresh installs and updates of existing
user databases.

### Build and Run — Do It Yourself

Check behaviour with `Native::test()` first. When a change needs a build to take effect or to be checked, run it
yourself from the Laravel root against a booted simulator or running emulator:

```bash
xcrun simctl list devices booted     # iOS simulator UDIDs

adb devices                          # Android serials

php artisan native:run ios <UDID> --no-tty -n
php artisan native:run android <SERIAL> --no-tty -n
```

Use the platform the user named; otherwise use whichever device is running. If no simulator or emulator is
available, say so and give the user the command instead. It returns once the app is installed and launched.

Ask first, or leave to the user:
- `--watch` and `native:watch` never exit, so don't block on them. Start one in the background only when the user
  wants hot reload.
- Release builds (`--build=release|bundle`), signing, `native:package` and store uploads.

Other commands: `native:open` (Xcode or Android Studio), `native:install` (install or upgrade the native shell).
The `./native` shortcut wraps the `native:` namespace. The Vite dev server is **opt-in** in v4: add `--vite` only
when the app uses JS/CSS HMR; native UI screens hot-reload without it. `npm run build -- --mode=ios|android` is only
needed for apps with web-view assets.

=== nativephp/mobile-browser/core rules ===

## nativephp/browser

Open URLs in system browser, in-app browser, and OAuth authentication sessions.

### PHP Usage (Livewire/Blade)

Use the `Browser` facade:

<code-snippet name="Using Browser Facade" lang="php">
use Native\Mobile\Facades\Browser;

// Open in in-app browser (keeps users in your app)
Browser::inApp('https://nativephp.com/mobile');

// Open in system browser (leaves your app)
Browser::open('https://nativephp.com/mobile');

// OAuth authentication with automatic redirect handling
Browser::auth('https://provider.com/oauth/authorize?client_id=123&redirect_uri=nativephp://127.0.0.1/auth/callback');
</code-snippet>

### JavaScript Usage (Vue/React/Inertia)

<code-snippet name="Using Browser in JavaScript" lang="javascript">
import { browser } from '#nativephp';

// Open in in-app browser
await browser.inApp('https://nativephp.com/mobile');

// Open in system browser
await browser.open('https://nativephp.com/mobile');

// OAuth authentication
await browser.auth('https://provider.com/oauth/authorize?client_id=123&redirect_uri=nativephp://127.0.0.1/auth/callback');
</code-snippet>

### Methods

- `Browser::inApp(string $url)` - Opens in embedded browser (SFSafariViewController/Chrome Custom Tabs)
- `Browser::open(string $url)` - Opens in device's default browser
- `Browser::auth(string $url)` - Opens OAuth authentication browser with automatic redirect handling

### When to Use Each Method

- **`inApp()`**: Keep users within your app for documentation, help pages, or related content
- **`open()`**: Use when full browser features are required for complex web applications
- **`auth()`**: Implement OAuth authentication flows with secure, automatic redirect handling

=== nativephp/mobile-ui/core rules ===

## nativephp/mobile-ui

Native UI components for NativePHP Mobile. Every element renders as a real
platform primitive — Material3 on Android, SwiftUI on iOS — not a webview
widget. Elements are declared in Blade with `<native:*>` tags or built
programmatically with the fluent `Native\Mobile\UI\Elements\*` API; both
paths serialize to the same wire tree.

### Core rules

- Visual styling is theme-driven ("Model 3"): buttons, inputs, toggles, and
  other controls take their colors, radii, and typography from the theme
  (`Native\Mobile\UI\Theme`). Use semantic props like `variant="primary"`
  instead of per-instance colors — per-instance visual overrides on these
  controls are intentionally ignored.
- Bind state with `native:model="property"` (works on toggle, checkbox, chip,
  slider, select, radio-group, button-group, tab-row, and the text inputs).
  Use `.live` / `.blur` / `.debounce.Xms` modifiers to control sync frequency.
- Wire callbacks with event attributes (`@tap`, `@change`, `@submit`,
  `@dismiss`) pointing at public methods on the component.
- `<native:date-picker>` handles dates, times, and both. Set `mode` to
  `date` (default), `time`, or `datetime`. Values are always wall-clock ISO
  strings — `2026-07-25`, `14:30`, `2026-07-25T14:30` — never offsets or
  epoch numbers, so they feed straight into `Carbon::parse()`. `value`,
  `min`, and `max` also accept any `DateTimeInterface`.
- On the picker, `timezone` (IANA) names the calendar the user picks in and
  never rewrites the value; `locale` (BCP-47) is display-only. Use
  `picker-style` = `compact` (default) / `inline` / `wheel` (NOT `display`,
  which is flex/layout display). `title`,
  `confirm-label`, and `cancel-label` are Android-only dialog chrome — pass
  translated strings. `min`/`max` are NOT supported for `mode="time"` (they
  throw), and sync-mode modifiers (`native:model.blur`) throw too — a picker
  always commits on selection.
- In tests, drive pickers with the `pickDate()` / `pickTime()` /
  `pickDateTime()` / `clearPicker()` macros and assert with
  `assertPicker()` / `assertPickerValue()` / `assertPickerEmpty()`.

<code-snippet name="Declaring native elements in Blade" lang="blade">
<native:column class="gap-4 p-4">
    <native:outlined-text-input label="Email" native:model.blur="email" />
    <native:date-picker label="Starts" mode="datetime" native:model="startsAt" />
    <native:toggle label="Notifications" native:model="notify" />
    <native:button variant="primary" @tap="save">Save</native:button>
</native:column>
</code-snippet>

### Theming & colors

- Everywhere a color is authored — theme tokens in `config/native-ui.php`,
  element color props (`->color()`, `headline-color`, badge `color`, swipe
  `tint`), and arbitrary-value classes (`bg-[#…]`) — the same grammar applies:
  - Tailwind palette names: `red-300`, `orange-800`
  - Special names: `white`, `black`, `transparent`
  - CSS hex: `#F00`, `#B91C1C`, and with alpha `#8B5CF680` (#RRGGBBAA order)
  - Opacity modifiers on any of the above: `red-300/20`, `#8B5CF6/50`
- Alpha-bearing hex is always authored in CSS `#RRGGBBAA` order; PHP converts
  to the native wire order — never hand-author Android-style `#AARRGGBB`.
- Dark mode: theme tokens carry a `dark` block (auto-derived when omitted),
  and `bg-theme-*` / `text-theme-*` / `border-theme-*` classes emit both
  modes automatically. This works for Blade-declared AND programmatically
  built elements (`Element->class()`).
- The theme token map is open-ended: add any semantic role your design
  needs (`success`, `warning`, `outline-variant`, …) to both `light` and
  `dark` blocks and the matching `*-theme-*` classes resolve immediately —
  never fall back to hardcoded hex for a missing role.
- `success`/`on-success` and `outline-variant` are first-class tokens: the
  native theme stores parse them (with fallbacks), and `variant="success"`
  works on `<native:button>` and `<native:badge>` for confirm / "safe to
  proceed" actions — prefer it over green hex or misusing `primary`.
- Theme classes accept opacity modifiers like every other color class:
  `bg-theme-primary/15` is the tonal-fill idiom (alpha applies to the dark
  companion too).
- In PHP — layout chrome builders (`->activeColor()`, `->backgroundColor()`),
  dynamic styling — read tokens with the appearance-aware `theme()` helper
  (`theme('primary')`) instead of raw `config()` paths or pasted hex.
- Disabled controls use the `surface-variant` (fill) + `on-surface-variant`
  (label) tokens on both platforms — tune disabled contrast by adjusting
  those two tokens, not per-component.
- Buttons render their variant token solid; for a softer tonal fill set
  opacity on the token itself (e.g. `'secondary' => 'fuchsia-500/70'`).
- `<native:icon>` accepts platform enum overrides as attributes —
  `:ios="Ios::House"` / `:android="Android::Home"` — matching the
  programmatic `Icon::make(ios: …, android: …)`.

<code-snippet name="Theme tokens accept the full color grammar" lang="php">
// config/native-ui.php
'light' => [
    'primary'   => 'violet-600',      // tailwind palette name
    'secondary' => 'fuchsia-500/70',  // with opacity → tonal fills
    'surface'   => '#F8FAFC',         // plain hex
    'accent'    => '#00AAA680',       // CSS alpha hex (#RRGGBBAA)
],
</code-snippet>

### Typography

- **Custom fonts.** Drop `.ttf`/`.otf`/`.ttc` files into the app's
  `resources/fonts/` and reference one by its filename (minus extension) with
  the `font` attribute: `font="Inter-Bold"` for `resources/fonts/Inter-Bold.ttf`.
  Works on `<native:text>`, `<native:button>`, and the text inputs; also fluent
  as `->font('Inter-Bold')`. The build's `copy_assets` hook bundles the files
  (iOS registers them by PostScript name, Android loads from `assets/fonts/`);
  an unresolved name falls back to the system font. Font size/weight still come
  from `text-*` / `font-*` classes and the theme.
- **Downloading fonts.** `php artisan native:font Lobster` (or `"Rock Salt"`,
  multiple families, `--weights=400,700`, `--italic`) downloads Google Fonts
  into `resources/fonts/` with ready-to-use token names — no API key.
- **Font aliases + app-wide default.** Name fonts semantically in
  `config/native-ui.php`: `'fonts' => ['default' => 'Inter-Regular',
  'accent' => 'DynaPuff-Regular']`. Use an alias anywhere a font token works
  (`font="accent"`, chrome `->font()`, layout `$font`); the `default` alias
  applies app-wide (superseding the older `font-family` token). Per-element
  `font` attributes and `font-serif`/`font-mono` classes still win over the
  default. `native:font --default` sets it for you.
- **Line height (leading).** `leading-none|tight|snug|normal|relaxed|loose`
  (unitless multipliers of the font size), plus arbitrary `leading-[1.4]`
  (multiplier) and `leading-[24px]` (absolute). Applies to `<native:text>` and
  the text inputs; button labels are single-line so it has no visible effect
  there. Only affects multi-line text. iOS caveat: SwiftUI's `Text` only exposes
  additive line spacing, so *increasing* leading (`relaxed`/`loose`, or a large
  `leading-[…px]`) is exact, but tightening below the font's natural line height
  (`none`/`tight`) is limited — measured against the actual font, so custom
  fonts aren't over-spaced. Android is exact both ways.

<code-snippet name="Custom font + line height" lang="blade">
<native:text font="Inter-Bold" class="text-2xl">Heading</native:text>
<native:text class="text-base leading-relaxed">
    A comfortably-spaced paragraph that wraps across several lines.
</native:text>
</code-snippet>

### Accessibility

Screen-reader support rides on two props that every element accepts:
`a11y-label` (what VoiceOver / TalkBack announces; maps to
`accessibilityLabel` on iOS and `contentDescription` on Android) and
`a11y-hint` (supplementary usage guidance, read after the label; maps to
`accessibilityHint` on iOS and is appended to the content description on
Android). Both are also available fluently as `->a11yLabel()` / `->a11yHint()`.

- ALWAYS set `a11y-label` on icon-only buttons, chips, and tabs — with no
  visible text there is nothing for the screen reader to announce.
- Icons are decorative by default: an `<native:icon>` without `a11y-label` is
  silent to screen readers. Give it a label only when the icon itself carries
  meaning.
- Use `alt` on `<native:image>` for meaningful images; omit it for purely
  decorative ones.
- Use `a11y-hint` sparingly, for supplementary guidance the label doesn't
  cover ("Double-tap to reorder"). Never repeat the label in the hint.
- List items with a trailing icon button take `trailing-a11y-label` to label
  that button separately from the row.
- Text scales with the user's system font size on both platforms
  automatically — don't hardcode layouts that break at larger type sizes.

<code-snippet name="Accessible icon-only controls" lang="blade">
<native:button icon="trash" a11y-label="Delete draft" a11y-hint="Deletes the draft permanently" @tap="deleteDraft" />
<native:icon name="checkmark.seal" a11y-label="Verified" />
<native:list-item headline="Team meeting" trailingIconButton="ellipsis" trailing-a11y-label="More options" />
</code-snippet>

<code-snippet name="Fluent a11y API" lang="php">
use Native\Mobile\UI\Elements\Button;

Button::make()
    ->icon('plus')
    ->a11yLabel('Add item')
    ->a11yHint('Adds a new item to the list')
    ->onPress('addItem');
</code-snippet>

=== unloc/nativephp-enhanced-splash/core rules ===

## unloc/nativephp-enhanced-splash

Controls the launch screen: seamless or single-phase splash on Android, and an app-icon or vector
launch screen on iOS.

Build-time only: no facade, no bridge functions, no runtime API. A `pre_compile` hook
(`nativephp:enhanced-splash:prepare`) rewrites the native project's splash assets, theme and
configuration after the core build has written its own. Never call it directly — it runs as part
of `native:run`.

### Configuration

Everything lives in `config/enhanced-splash.php` (publish with
`php artisan vendor:publish --tag=enhanced-splash-config`). Never patch the generated `nativephp/`
project by hand — a rebuild overwrites it.

<code-snippet name="config/enhanced-splash.php" lang="php">
return [
    'ios' => [
        // 'image' = full-bleed LaunchImage | 'icon' = app icon centered on the background
        'mode' => env('ENHANCED_SPLASH_IOS_MODE', 'image'),
        'background' => env('ENHANCED_SPLASH_IOS_BACKGROUND', '#FFFFFF'),
        'background_dark' => env('ENHANCED_SPLASH_IOS_BACKGROUND_DARK', '#000000'),
        'icon_size' => env('ENHANCED_SPLASH_IOS_ICON_SIZE', 160),
        'icon_rounded' => env('ENHANCED_SPLASH_IOS_ICON_ROUNDED', true),
        'icon_shadow' => env('ENHANCED_SPLASH_IOS_ICON_SHADOW', false),
    ],
    'android' => [
        // 'image' = Compose splash overlay | 'icon' = hold the system splash
        'mode' => env('ENHANCED_SPLASH_ANDROID_MODE', 'image'),
        'background' => env('ENHANCED_SPLASH_ANDROID_BACKGROUND', '#000000'),
        'background_dark' => env('ENHANCED_SPLASH_ANDROID_BACKGROUND_DARK', '#000000'),
    ],
];
</code-snippet>

Defaults reproduce core's own behavior, so installing the plugin changes nothing until configured.
Android's black is core's own window color, chosen to match the splash overlay's backdrop.

- Colors take `#RRGGBB` or `#RRGGBBAA`, always in CSS channel order — the alpha byte is reordered
  for each platform at build time. Never author Android's `#AARRGGBB` here.
- `#` starts a comment in `.env` — quote hex values there: `ENHANCED_SPLASH_IOS_BACKGROUND='#FFFFFF'`.
- Changing any of this requires a rebuild (`php artisan native:run ios|android`) — the hook only
  runs at build time.

### Input files

Splash images stay where core expects them: `public/splash.png`, `public/splash-dark.png`. On iOS a
`public/splash.svg` (and optional `public/splash-dark.svg`) is preferred over the PNGs and kept as a
vector. The icon modes read the app icon core installs from `public/icon.png`; with no app icon
present the launch screen is core's own.

### Android

- `android.background` / `background_dark` apply under **both** modes. Under `image` they paint all
  three surfaces a cold start crosses — the Android 12+ system splash, the app window, and the
  Compose overlay's backdrop — so the hand-off to `splash.png` is seamless instead of a cut through
  core's black. Set them to the color the splash image sits on.
- That color is also the app window's background after the splash, so it shows through any frame the
  UI has not painted yet. Keep it close to the app's own surface color.
- `android.mode = 'icon'` hands the splash to the platform: the system splash is held until first
  content and the Compose overlay never draws. It shows the launcher icon on `background`, so
  `splash.png` goes unused — prefer `image` when the artwork matters.

### iOS

- `ios.mode = 'icon'` draws the app icon centered on the background instead of a splash image, on
  both the launch storyboard and the SwiftUI splash, so the hand-off between them is invisible.
  `splash.svg` applies only in `image` mode.
- `ios.icon_size` (default 160 points, matching Android's system splash) sizes the icon itself, not
  the generated asset — `icon_shadow` enlarges the asset but not the icon on screen.
- `ios.icon_rounded` masks the icon to the iOS squircle so it matches the home screen. Turn it off
  for artwork that is already shaped, or a logo that should not be clipped.
- `ios.icon_shadow` lifts the icon off the background. Off by default: iOS icons are flat.

### Housekeeping

- Package defaults are merged recursively, so a `config/enhanced-splash.php` published before a new
  key existed still picks that key up. Re-publish with `--force` only to see the new comments.
- To remove the plugin: `composer remove unloc/nativephp-enhanced-splash`, then
  `php artisan native:install` to regenerate the native project from core's own sources. Not needed
  to change settings — every build undoes the previous one first.

=== unloc/nativephp-svg-component/core rules ===

## unloc/nativephp-svg-component

Renders SVG as a native graphic — SwiftDraw on iOS, coil-svg on Android. The `<native:svg>` element
mirrors core's `<native:image>`: same `src` / `fit` / `tintColor` / `alt` props, same Tailwind
`object-*` and sizing classes, same silent-failure behaviour.

<code-snippet name="Rendering an SVG" lang="blade">
<native:svg :src="resource_path('svg/logo.svg')" alt="Logo" class="h-16 w-full object-contain"/>
</code-snippet>

### Rules

- NEVER write literal SVG markup into an attribute in a native Blade view. Every registered element
  type is also a bare tag, so a literal `<svg …>` compiles to an *element*, not a string. Pass it from
  PHP with `:src="$markup"` — PHP is never precompiled, so the markup survives exactly.
- Author SVGs to SVG 1.1. Android rejects the whole document on an attribute value it doesn't know, so
  one SVG 2 value renders a blank frame while iOS ignores it and draws the rest — it presents as "fine
  on iOS, blank on Android" with nothing in the logs.
- Always author a `viewBox`. Without one the document has no intrinsic size: iOS renders nothing and
  Android draws it unscaled in the corner. An explicit frame does not rescue either.
- Prefer `resource_path()` absolute paths for app-bundled SVGs — the Laravel tree is copied into the
  bundle wholesale.
- `fill` stretches on both axes, `cover` keeps aspect and crops — CSS `object-fit` semantics, and the
  platforms agree on both. Reach for `fill` only when distortion is harmless (a barcode, a rule).
- Set `fit` only when you mean to. Leaving it unset lets an `object-*` class win, and with no
  `object-*` class at all the graphic draws at its document size like an `<img>` with no CSS.
- Set `alt` on meaningful graphics; omit it on decorative ones and the graphic is hidden from
  VoiceOver and TalkBack.
- `tintColor` replaces every non-transparent pixel with the colour — it is a template tint, not an
  overlay. It takes the same grammar as other element colour props: palette names (`red-300`),
  `white` / `black` / `transparent`, hex, and `/N` opacity modifiers. Use `theme('primary')` for a
  theme token.
- Filters and SMIL animation never render; markers render on Android but not iOS. Filters and markers
  drop cleanly — the shape still draws. Flatten `<text>` to paths when the platforms must match.

### Sources

`src` accepts a `data:` URI, raw markup, an `https://` URL, an absolute or `file://` path, or a
bundled asset name. Data URIs are decoded to markup in PHP by `Svg::src()`, so both platforms see one
canonical form — do not add data-URI handling to the renderers. An undecodable payload empties `src`
and logs a warning; it never throws, because `src` is routinely runtime data from an API or the
database.

=== victorycodedev/toastkit/core rules ===

## ToastKit

ToastKit adds native toast notifications to NativePHP Mobile apps. It is controlled from PHP/JS; the native overlay
renders independently. There is no Blade/EDGE component to add.

### Installation assumption

ToastKit is already installed and registered in this app (`composer require victorycodedev/toastkit`, then `php artisan
native:plugin:register victorycodedev/toastkit`). Native code changes require `php artisan native:run` (or `php artisan
native:install --force`) before toasts render.

### Facade

```php
use Victorycodedev\ToastKit\Facades\Toast;
```

### Public methods

- `Toast::make(?string $message = null)` — start a builder.
- `Toast::success(string $message)` / `error` / `warning` / `info` / `neutral` — variant shortcuts returning a
`PendingToast`.
- `Toast::update(string $id)` — start a `PendingToastUpdate` for an existing toast.
- `Toast::dismiss(string $id)` — dismiss a toast.
- `Toast::dismissAll()` — dismiss everything.

Every builder requires a final `->show()` call. `show()` returns the toast ID (a UUID, or the custom ID from `->id()`).

### PendingToast / PendingToastUpdate methods

`message()`, `title(?string)`, `success()`, `error()`, `warning()`, `info()`, `neutral()`, `variant()`, `icon(name,
ios:, android:)`, `position()`, `duration(int)`, `persistent(bool = true)`, `animation()`, `swipeToDismiss(bool =
true)`, `dismissible(bool = true)`, `action(label, id)`, `background()`, `foreground()`, `iconColor()`, `actionColor()`,
`cornerRadius()`, `padding()`, `shadow(bool = true)`, `queue()`, `stack()`, `strategy()`, `maxVisible(int)`, `show()`.

`PendingToast` also has `id(string)`. `PendingToastUpdate` does not — its ID is fixed at `Toast::update($id)`.

### Variants

`success`, `error`, `warning`, `info`, `neutral`. Each applies native color/icon defaults; explicit style options
override them.

### IDs

`show()` returns a UUID by default. Supply `->id('my-id')` for a stable reference. Use that ID for `Toast::update($id)`
and `Toast::dismiss($id)`.

### Updates are sparse

Only properties explicitly set on `Toast::update($id)` change. Unspecified values are preserved. Timer semantics: no
timing change preserves the deadline; `duration()` starts a new timer; `duration()` → `persistent()` cancels the timer;
`persistent()` → `duration()` starts a new timer.

### Queue and stack

Default strategy is `queue` (FIFO, one at a time). `stack()` shows up to `maxVisible` (default 3) at once; overflow
waits FIFO. Queue waiting time does not consume the duration — timers start only when a toast becomes visible.

### Styling

Colors accept `#RGB`, `#RRGGBB`, `#AARRGGBB` (alpha first) and are uppercased. `->dismissible()` shows a close control;
`->swipeToDismiss()` controls the gesture (on by default).

### Actions

```php
Toast::error('Connection lost')->action(label: 'Retry', id: 'retry')->show();
```

Actions are native buttons — never pass PHP closures to `action()`. Pressing an action dismisses the toast.

### Events

Listen with NativePHP's `#[OnNative]` attribute. Payloads arrive as named method parameters.

- `Victorycodedev\ToastKit\Events\ToastShown` — `toastId`
- `Victorycodedev\ToastKit\Events\ToastDismissed` — `toastId`, `reason` (`timeout`, `swipe`, `programmatic`, `action`,
`replaced`)
- `Victorycodedev\ToastKit\Events\ToastActionPressed` — `toastId`, `actionId`

```php
use Native\Mobile\Attributes\OnNative;
use Victorycodedev\ToastKit\Events\ToastActionPressed;

#[OnNative(ToastActionPressed::class)]
public function handleToastAction(string $toastId, string $actionId): void
{
// ...
}
```

### FakeBridge test macros

In your own component tests (no device needed):

```php
Native::test(ProfileScreen::class)
->call('save')
->assertToastShownWithMessage('Profile updated');
```

Macros: `assertToastShown()`, `assertToastShownWithMessage($message)`, `assertToastShownWithId($id)`,
`assertToastUpdated($id, $changesFilter = null)`, `assertToastDismissed($id)`, `assertAllToastsDismissed()`.

### JavaScript API

`import { Toast, Show, Update, Dismiss, DismissAll } from 'victorycodedev/toastkit';`

`Toast.make/success/error/warning/info/neutral` build toasts; `.show()` sends them.
`Toast.update(id).message(...).show()`, `Toast.dismiss(id)`, `Toast.dismissAll()`. Raw bridge functions
`Show/Update/Dismiss/DismissAll` are also exported. npm publishing is future work; the source lives in `resources/js/`.

### Common mistakes

- Forgetting `->show()` — builders do nothing until shown.
- Expecting `Toast::update()` to mutate unspecified values — updates are sparse.
- Passing invalid colors (must be `#RGB`/`#RRGGBB`/`#AARRGGBB`).
- Expecting a queued toast's timer to run while it waits — timers start on visibility.
- Passing PHP closures to `action()` — actions are native buttons identified by `id`.
- Treating ToastKit as a Blade component — it is controlled from PHP and rendered by a native overlay.

</laravel-boost-guidelines>
