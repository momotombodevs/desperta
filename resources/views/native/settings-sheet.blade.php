@use('App\Icons\Android')
@use('App\Icons\Ios')

<native:bottom-sheet :visible="$settingsOpen" detents="large" @dismiss="closeSettings">
    <native:scroll-view class="w-full bg-theme-surface">
        <native:column class="w-full gap-5 p-6">

                <native:text font="accent" class="text-2xl text-theme-on-surface">{{ __('app.settings') }}</native:text>



            <native:row class="w-full items-center justify-center gap-3">
                <native:svg :src="public_path('images/brand/desperta-mark.svg')" :width="120" :height="120" :fit="1"
                            :alt="__('app.app_name')"/>
            </native:row>


            <native:column class="w-full gap-2">
                <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.appearance') }}</native:text>
                <native:button-group ref="appearance-selector"
                                     :options="[__('app.appearance_system'), __('app.appearance_light'), __('app.appearance_dark')]"
                                     native:model="appearanceSelection" :a11y-label="__('app.appearance')"/>
            </native:column>

            <native:column class="w-full gap-2">
                <native:row class="items-center gap-2">
                    <native:text font="accent" class="text-base text-theme-on-surface">{{ __('app.language') }}</native:text>
                </native:row>
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
                <native:tab-row ref="challenge-theme-selector" native:model="challengeThemeSelection"
                                :a11y-label="__('app.challenge_theme')">
                    <native:tab :label="__('challenges.nicaragua.name')" icon="flag" :a11y-label="__('challenges.nicaragua.name')"/>
                    <native:tab :label="__('challenges.math.name')" icon="calculate" :a11y-label="__('challenges.math.name')"/>
                    <native:tab :label="__('challenges.general_knowledge.name')" icon="globe" :a11y-label="__('challenges.general_knowledge.name')"/>
                </native:tab-row>
            </native:column>
            <native:button ref="open-history" variant="secondary"  class="w-full" size="lg"
                           @tap="openHistoryFromSettings"
                           :a11y-label="__('app.alarm_history')">
                {{ __('app.alarm_history') }}
            </native:button>
        </native:column>
    </native:scroll-view>
</native:bottom-sheet>
