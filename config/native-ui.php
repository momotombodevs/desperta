<?php

/**
 * Native UI — Theme Tokens
 *
 * Published via `php artisan vendor:publish --tag=native-ui-config`.
 * Edit to customize your app's visual identity in one place.
 *
 * For dynamic per-tenant theming, use Nativephp\NativeUi\Theme::merge([...])
 * from a service provider. Runtime merges deep-merge on top of these values.
 *
 * Decision log: /docs/NATIVE-UI-REWRITE-PLAN.md (D — theme layer)
 */

return [

    /*
    |---------------------------------------------------------------------------
    | Theme
    |---------------------------------------------------------------------------
    |
    | 17 color tokens, 4 radii, 4 font sizes, font family.
    |
    | "on-X" means "color of content placed ON a surface of color X"
    |   — i.e., text/icons on that background.
    |
    | Color tokens accept:
    |   - CSS hex: '#B91C1C', '#F00', or with alpha '#8B5CF680' (#RRGGBBAA)
    |   - Tailwind palette names: 'red-300', 'orange-800'
    |   - Opacity modifiers on either: 'red-300/20', '#8B5CF6/50'
    |
    | Dark mode is auto-derived from `light` when `dark` is not set. To opt
    | into explicit dark tokens, fill out the `dark` block.
    |
    | The default pairs meet WCAG AA (4.5:1) — if you customize, keep each
    | `on-*` color at 4.5:1 contrast against its background token.
    |
    */

    'theme' => [

        'light' => [
            // Primary brand color — used for filled buttons, active states, key accents.
            'primary' => '#1D4ED8',
            'on-primary' => '#FFFFFF',

            // Secondary / muted action color.
            'secondary' => '#64748B',
            'on-secondary' => '#FFFFFF',

            // Surface = cards, sheets, dialogs. Background = page root.
            'surface' => '#FFFFFF',
            'on-surface' => '#0F172A',
            'background' => '#F8FAFC',
            'on-background' => '#0F172A',

            // Surface variant = filled text fields, muted tonal surfaces.
            // on-surface-variant = muted label/hint text on those surfaces.
            'surface-variant' => '#EEF2F7',
            'on-surface-variant' => '#64748B',

            // Outline = neutral borders (text fields, dividers, cards).
            'outline' => '#E2E8F0',

            // Destructive actions — maps to `variant="destructive"` on components.
            'destructive' => '#B42318',
            'on-destructive' => '#FFFFFF',

            // Warning — attention states such as missing alarm permissions.
            'warning' => '#B45309',
            'on-warning' => '#FFFFFF',

            'success' => '#15803D',
            'on-success' => '#FFFFFF',
            'sunrise' => '#C2410C',
            'on-sunrise' => '#FFFFFF',
        ],

        'dark' => [
            // Leave empty or partial to auto-derive from `light` (luminance inversion).
            // Specify any token here to override the derived value.
            'primary' => '#2563EB',
            'on-primary' => '#FFFFFF',

            'secondary' => '#CBD5E1',
            'on-secondary' => '#0F172A',

            'surface' => '#111827',
            'on-surface' => '#F8FAFC',
            'background' => '#0B1120',
            'on-background' => '#F8FAFC',

            'surface-variant' => '#1E293B',
            'on-surface-variant' => '#CBD5E1',

            'outline' => '#334155',

            'destructive' => '#FCA5A5',
            'on-destructive' => '#0F172A',

            'warning' => '#FCD34D',
            'on-warning' => '#422006',

            'success' => '#86EFAC',
            'on-success' => '#052E16',
            'sunrise' => '#FDBA74',
            'on-sunrise' => '#431407',
        ],

        // Corner radii (points / dp).
        'radius-sm' => 4,
        'radius-md' => 8,
        'radius-lg' => 16,
        'radius-full' => 9999,

        // Font size scale (points / sp).
        'font-sm' => 14,
        'font-md' => 16,
        'font-lg' => 20,
        'font-xl' => 24,
    ],

    'fonts' => [
        'default' => 'System',
        'accent' => 'Archivo+Black-Regular',
        'lobster' => 'Lobster+Two-Regular',
    ],

];
