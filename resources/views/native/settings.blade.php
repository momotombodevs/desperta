@use('App\Icons\Android')

<native:top-bar :title="__('app.settings')" show-navigation-icon />

<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full gap-4 p-6">
        <native:row class="w-full justify-center">
            <native:svg :src="public_path('images/brand/desperta-mark.svg')" :width="92" :height="92" :fit="1"
                        :alt="__('app.app_name')"/>
        </native:row>

        <native:pressable ref="open-habits" class="w-full rounded-xl border border-theme-outline bg-theme-surface-variant p-4"
                          @navigate="'/settings/habits'" :a11y-label="__('app.habits')">
            <native:row class="w-full items-center gap-3">
                <native:icon :android="Android::BarChart" class="text-theme-primary" size="24"/>
                <native:column class="flex-1 gap-1">
                    <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.habits') }}</native:text>
                </native:column>
                <native:icon :android="Android::ChevronRight" class="text-theme-on-surface" size="20"/>
            </native:row>
        </native:pressable>

        <native:pressable ref="open-history" class="w-full rounded-xl border border-theme-outline bg-theme-surface-variant p-4"
                          @navigate="'/settings/history'" :a11y-label="__('app.view_history')">
            <native:row class="w-full items-center gap-3">
                <native:icon :android="Android::History" class="text-theme-primary" size="24"/>
                <native:column class="flex-1 gap-1">
                    <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.alarm_history') }}</native:text>
                </native:column>
                <native:icon :android="Android::ChevronRight" class="text-theme-on-surface" size="20"/>
            </native:row>
        </native:pressable>

        <native:column class="w-full gap-2">
            <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.appearance') }}</native:text>
            <native:button-group ref="appearance-selector"
                                 :options="[__('app.appearance_system'), __('app.appearance_light'), __('app.appearance_dark')]"
                                 native:model="appearanceSelection" :a11y-label="__('app.appearance')"/>
        </native:column>

        <native:column class="w-full gap-2">
            <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.language') }}</native:text>
            <native:row class="w-full gap-3">
                <native:pressable ref="language-spanish"
                                  class="flex-1 rounded-xl border p-3 {{ $languagePreference === 'es_NI' ? 'border-theme-primary bg-theme-primary/15' : 'border-theme-outline bg-theme-surface-variant' }}"
                                  @tap="selectLanguage('es_NI')" :a11y-label="__('app.select_spanish')">
                    <native:row class="items-center gap-3">
                        <native:svg :src="public_path('images/flags/nicaragua.svg')" :width="32" :height="22" :fit="1" :alt="__('app.nicaragua_flag')"/>
                        <native:text class="flex-1 text-sm text-theme-on-surface">{{ __('app.language_spanish') }}</native:text>
                        @if ($languagePreference === 'es_NI')
                            <native:icon name="check" class="text-theme-primary" size="18"/>
                        @endif
                    </native:row>
                </native:pressable>

                <native:pressable ref="language-english"
                                  class="flex-1 rounded-xl border p-3 {{ $languagePreference === 'en' ? 'border-theme-primary bg-theme-primary/15' : 'border-theme-outline bg-theme-surface-variant' }}"
                                  @tap="selectLanguage('en')" :a11y-label="__('app.select_english')">
                    <native:row class="items-center gap-3">
                        <native:svg :src="public_path('images/flags/united-states.svg')" :width="32" :height="22" :fit="1" :alt="__('app.united_states_flag')"/>
                        <native:text class="flex-1 text-sm text-theme-on-surface">{{ __('app.language_english') }}</native:text>
                        @if ($languagePreference === 'en')
                            <native:icon name="check" class="text-theme-primary" size="18"/>
                        @endif
                    </native:row>
                </native:pressable>
            </native:row>
        </native:column>

        <native:column class="w-full gap-2">
            <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.challenge_theme') }}</native:text>
            @php($challengeThemes = [
                'nicaragua' => __('challenges.nicaragua.name'),
                'math' => __('challenges.math.name'),
                'general_knowledge' => __('challenges.general_knowledge.name'),
            ])
            <native:select ref="challenge-theme-selector" :label="__('app.challenge_theme')"
                           :options="array_values($challengeThemes)" :value="$challengeThemes[$challengeThemePreference]"
                           @change="selectChallengeTheme" :a11y-label="__('app.challenge_theme')"/>
        </native:column>

        <native:pressable ref="privacy-policy" class="w-full rounded-xl border border-theme-outline bg-theme-surface-variant p-4"
                          @tap="openPrivacyPolicy" :a11y-label="__('app.privacy_policy_a11y')" :a11y-hint="__('app.privacy_policy_hint')">
            <native:row class="w-full items-center gap-3">
                <native:icon :android="Android::PrivacyTip" class="text-theme-primary" size="24"/>
                <native:column class="flex-1 gap-1">
                    <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.privacy_policy') }}</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.privacy_policy_summary') }}</native:text>
                </native:column>
                <native:icon :android="Android::ChevronRight" class="text-theme-on-surface" size="20"/>
            </native:row>
        </native:pressable>

        <native:divider class="w-full" />
        <native:pressable ref="momotombo-devs" class="w-full p-3" @tap="openMomotomboDevs"
                          :a11y-label="__('app.momotombo_devs_a11y')" :a11y-hint="__('app.momotombo_devs_hint')">
            <native:row class="w-full justify-center">
                <native:text class="text-sm text-theme-on-surface-variant">{{ __('app.crafted_with_passion') }}</native:text>
            </native:row>
        </native:pressable>
    </native:column>
</native:scroll-view>
