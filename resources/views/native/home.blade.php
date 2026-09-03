@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:top-bar :title="__('app.app_name')" :subtitle="__('app.tagline')" display-mode="large">
    <native:top-bar-action
        id="settings"
        ref="settings"
        :ios-icon="Ios::Gearshape"
        :android-icon="Android::Settings"
        :label="__('app.settings')"
        @tap="openSettings"
        :a11y-label="__('app.settings')"
    />
</native:top-bar>

<native:list ref="alarm-list" class="w-full h-full bg-theme-background p-5">
    @if ($this->nextAlarm !== null)
        <native:column class="w-full gap-2 rounded-2xl bg-theme-sunrise p-6">
            <native:text font="accent" class="text-sm text-theme-on-sunrise">{{ __('app.next_alarm') }}</native:text>
            <native:text font="accent" class="text-5xl text-theme-on-sunrise">
                {{ $this->nextAlarm->displayTime() }}
            </native:text>
            <native:text class="text-base text-theme-on-sunrise">
                {{ $this->nextAlarm->label }} · {{ $this->nextAlarm->repeatLabel() }}
            </native:text>
        </native:column>
        <native:spacer :height="24"/>
    @endif

    @if ($this->alarms->isEmpty())
        <native:column ref="home-screen" class="w-full h-full items-center justify-center">
            <native:icon
                :ios="Ios::BellSlash"
                :android="Android::AlarmOff"
                :opacity="$emptyStateVisible ? 1 : 0"
                :scale="$emptyStateVisible ? 1 : 0.92"
                :animate-duration="180"
                animate-easing="ease-out"
                class="text-theme-secondary"
                size="96"
                :a11y-label="__('app.no_alarms_a11y')"
            />
        </native:column>
    @else
        <native:column ref="home-screen" class="w-full gap-3">
            <native:text font="accent"
                         class="text-lg text-theme-on-background">{{ __('app.your_alarms') }}</native:text>
        </native:column>
        <native:spacer :height="12"/>

        @foreach ($this->alarms as $alarm)
            <native:list-item ref="edit-alarm-{{ $alarm->id }}" key="alarm-{{ $alarm->id }}"
                              class="w-full rounded-xl border border-theme-outline bg-theme-surface"
                              :headline="$alarm->displayTime()" :supporting="$alarm->label.' · '.$alarm->repeatLabel()"
                              leadingIcon="alarm" :leadingIconColor="theme('primary')"
                              :containerColor="theme('surface')"
                              :trailingSwitch="$alarm->enabled"
                              on-trailing-change="toggleAlarm('{{ $alarm->id }}')"
                              :trailing-a11y-label="__('app.alarm_active')"
                              @tap="editAlarm('{{ $alarm->id }}')"
                              on-swipe-delete="confirmDeleteAlarm('{{ $alarm->id }}')"
                              :a11y-label="__('app.edit_alarm').' '.$alarm->displayTime()"
                              :a11y-hint="__('app.swipe_to_delete')"/>
            @if (! $loop->last)
                <native:spacer :height="12"/>
            @endif
        @endforeach
    @endif
</native:list>

<native:fab ref="create-alarm" :ios-icon="Ios::Plus" :android-icon="Android::Add" :label="__('app.create_alarm')"
            @tap="createAlarm" :a11y-label="__('app.create_alarm')"/>

<native:bottom-sheet :visible="$settingsOpen" detents="0.9" @dismiss="closeSettings">
    <native:scroll-view class="w-full bg-theme-surface">
        <native:column class="w-full gap-5 p-6">
            <native:row class="w-full items-center gap-3">
                <native:image
                    :src="asset('images/brand/desperta-mark.svg')"
                    :width="40"
                    :height="40"
                    :fit="1"
                    :alt="__('app.app_name')"
                />
                <native:icon :ios="Ios::GearshapeFill" :android="Android::Settings" class="text-theme-primary"
                             size="24"/>
                <native:text font="accent" class="text-2xl text-theme-on-surface">{{ __('app.settings') }}</native:text>
            </native:row>

            <native:column class="w-full gap-2">
                <native:row class="items-center gap-2">
                    <native:icon :ios="Ios::SunMaxFill" :android="Android::LightMode" class="text-theme-secondary"
                                 size="18"/>
                    <native:text font="accent"
                                 class="text-base text-theme-on-surface">{{ __('app.appearance') }}</native:text>
                </native:row>
                <native:button-group
                    ref="appearance-selector"
                    :options="[__('app.appearance_system'), __('app.appearance_light'), __('app.appearance_dark')]"
                    native:model="appearanceSelection"
                    :a11y-label="__('app.appearance')"
                />
            </native:column>

            <native:column class="w-full gap-2">
                <native:row class="items-center gap-2">
                    <native:icon :ios="Ios::GlobeAmericasFill" :android="Android::Language" class="text-theme-secondary"
                                 size="18"/>
                    <native:text font="accent"
                                 class="text-base text-theme-on-surface">{{ __('app.language') }}</native:text>
                </native:row>
                <native:row class="w-full gap-3">
                    <native:pressable
                        ref="language-spanish"
                        class="flex-1 rounded-xl border p-3 {{ $languagePreference === 'es_NI' ? 'border-theme-primary bg-theme-primary/15' : 'border-theme-outline bg-theme-surface-variant' }}"
                        @tap="selectLanguage('es_NI')"
                        :a11y-label="__('app.select_spanish')"
                    >
                        <native:row class="items-center gap-3">
                            <native:image :src="asset('images/flags/nicaragua.svg')" :width="32" :height="22" :fit="1"
                                          :alt="__('app.nicaragua_flag')"/>
                            <native:text
                                class="flex-1 text-sm text-theme-on-surface">{{ __('app.language_spanish') }}</native:text>
                            @if ($languagePreference === 'es_NI')
                                <native:icon name="check" class="text-theme-primary" size="18"/>
                            @endif
                        </native:row>
                    </native:pressable>

                    <native:pressable
                        ref="language-english"
                        class="flex-1 rounded-xl border p-3 {{ $languagePreference === 'en' ? 'border-theme-primary bg-theme-primary/15' : 'border-theme-outline bg-theme-surface-variant' }}"
                        @tap="selectLanguage('en')"
                        :a11y-label="__('app.select_english')"
                    >
                        <native:row class="items-center gap-3">
                            <native:image :src="asset('images/flags/united-states.svg')" :width="32" :height="22"
                                          :fit="1" :alt="__('app.united_states_flag')"/>
                            <native:text
                                class="flex-1 text-sm text-theme-on-surface">{{ __('app.language_english') }}</native:text>
                            @if ($languagePreference === 'en')
                                <native:icon name="check" class="text-theme-primary" size="18"/>
                            @endif
                        </native:row>
                    </native:pressable>
                </native:row>
            </native:column>

            <native:column class="w-full gap-2">
                <native:row class="items-center gap-2">
                    <native:icon :ios="Ios::GraduationcapFill" :android="Android::School" class="text-theme-secondary"
                                 size="18"/>
                    <native:text font="accent"
                                 class="text-base text-theme-on-surface">{{ __('app.challenge_theme') }}</native:text>
                </native:row>
                <native:tab-row ref="challenge-theme-selector" native:model="challengeThemeSelection"
                                :a11y-label="__('app.challenge_theme')">
                    <native:tab :label="__('challenges.nicaragua.name')" icon="flag"
                                :a11y-label="__('challenges.nicaragua.name')"/>
                    <native:tab :label="__('challenges.math.name')" icon="calculate"
                                :a11y-label="__('challenges.math.name')"/>
                    <native:tab :label="__('challenges.general_knowledge.name')" icon="globe"
                                :a11y-label="__('challenges.general_knowledge.name')"/>
                </native:tab-row>
            </native:column>
        </native:column>
    </native:scroll-view>
</native:bottom-sheet>
