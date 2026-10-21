<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/** A card-style row that opens another screen, used by Settings and Progress. */
final class NavigationRow extends NativeComponent
{
    public string $target = '';

    public string $url = '';

    /** Material symbol name, e.g. `bar_chart`. */
    public string $icon = '';

    public string $title = '';

    public string $subtitle = '';

    public string $badge = '';

    public function render(): View
    {
        return view('native.navigation-row');
    }
}
