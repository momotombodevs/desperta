@use('App\Icons\Android')

<native:pressable ref="{{ $target }}" class="w-full rounded-xl border border-theme-outline bg-theme-surface p-4"
                  @navigate="$url" :press-scale="0.98" :a11y-label="$title">
    <native:row class="w-full items-center gap-3">
        <native:icon :android="$icon" class="text-theme-primary" size="24"/>
        <native:column class="flex-1 gap-1">
            <native:text font="accent" class="text-base text-theme-on-surface">{{ $title }}</native:text>
            @if ($subtitle !== '')
                <native:text class="text-sm text-theme-on-surface-variant">{{ $subtitle }}</native:text>
            @endif
        </native:column>
        @if ($badge !== '')
            <native:row class="items-center rounded-full bg-theme-primary/15 px-3 py-1">
                <native:text class="text-xs text-theme-primary">{{ $badge }}</native:text>
            </native:row>
        @endif
        <native:icon :android="Android::ChevronRight" class="text-theme-on-surface" size="20"/>
    </native:row>
</native:pressable>
