<?php

namespace App\NativeComponents;

use App\AlarmScheduling\ResumesActiveAlarm;
use App\Application\Challenges\ChallengeTheme;
use App\Application\Preferences\AppPreferences;
use App\Models\DailyQuestion;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

final class Settings extends NativeComponent
{
    use ResumesActiveAlarm;

    /** @var list<string> */
    private const array AppearancePreferences = ['system', 'light', 'dark'];

    public string $appearancePreference = 'system';

    public int $appearanceSelection = 0;

    public string $languagePreference = 'es_NI';

    public string $challengeThemePreference = 'nicaragua';

    public function mount(): void
    {
        $preferences = app(AppPreferences::class);
        $preferences->applyLanguage();
        $preferences->applyAppearance();
        $this->appearancePreference = $preferences->appearance();
        $this->languagePreference = $preferences->language();
        $this->challengeThemePreference = $preferences->challengeTheme();
        $this->appearanceSelection = $this->selectionFor(self::AppearancePreferences, $this->appearancePreference);
    }

    public function updatedAppearanceSelection(): void
    {
        $this->appearancePreference = self::AppearancePreferences[$this->appearanceSelection] ?? self::AppearancePreferences[0];

        app(AppPreferences::class)->setAppearance($this->appearancePreference);
    }

    public function selectLanguage(string $language): void
    {
        $this->languagePreference = $language;

        app(AppPreferences::class)->setLanguage($language);
    }

    public function selectChallengeTheme(string $label): void
    {
        $theme = array_search($label, ChallengeTheme::labels(), true);

        if ($theme === false) {
            return;
        }

        $this->challengeThemePreference = $theme;

        app(AppPreferences::class)->setChallengeTheme($theme);
    }

    #[Computed]
    public function dailyQuestionAnswered(): bool
    {
        return DailyQuestion::query()
            ->where('date', CarbonImmutable::now((string) config('app.alarm_timezone'))->toDateString())
            ->whereNotNull('answered_at')
            ->exists();
    }

    public function openMomotomboDevs(): void
    {
        Browser::inApp('https://momotombo.dev/');
    }

    public function openPrivacyPolicy(): void
    {
        Browser::inApp((string) config('services.desperta.privacy_policy_url'));
    }

    public function render(): View
    {
        return view('native.settings');
    }

    /** @param list<string> $options */
    private function selectionFor(array $options, string $value): int
    {
        $selection = array_search($value, $options, true);

        return $selection === false ? 0 : $selection;
    }
}
