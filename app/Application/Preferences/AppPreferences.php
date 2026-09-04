<?php

namespace App\Application\Preferences;

use App\Models\AppPreference;
use Illuminate\Contracts\Translation\Translator;
use InvalidArgumentException;
use Momotombo\NativephpAppearance\Facades\Appearance as NativeAppearance;

class AppPreferences
{
    private const string AppearanceKey = 'appearance';

    private const string LanguageKey = 'language';

    private const string ChallengeThemeKey = 'challenge_theme';

    private const string ChallengeOrderKeyPrefix = 'challenge_order_';

    /** @var list<string> */
    private const array Appearances = ['system', 'light', 'dark'];

    /** @var list<string> */
    private const array Languages = ['es_NI', 'en'];

    /** @var list<string> */
    private const array ChallengeThemes = ['nicaragua', 'math', 'general_knowledge'];

    public function __construct(private readonly Translator $translator) {}

    public function appearance(): string
    {
        return $this->value(self::AppearanceKey, 'system');
    }

    public function language(): string
    {
        return $this->value(self::LanguageKey, 'es_NI');
    }

    public function challengeTheme(): string
    {
        return $this->value(self::ChallengeThemeKey, 'nicaragua');
    }

    public function setAppearance(string $appearance): void
    {
        $this->store(self::AppearanceKey, $appearance, self::Appearances);
        NativeAppearance::set($appearance);
    }

    public function setLanguage(string $language): void
    {
        $this->store(self::LanguageKey, $language, self::Languages);
        $this->translator->setLocale($language);
    }

    public function setChallengeTheme(string $challengeTheme): void
    {
        $this->store(self::ChallengeThemeKey, $challengeTheme, self::ChallengeThemes);
    }

    public function lastChallengeOrder(string $theme): ?string
    {
        return AppPreference::query()->where('key', self::ChallengeOrderKeyPrefix.$theme)->value('value');
    }

    public function rememberChallengeOrder(string $theme, string $fingerprint): void
    {
        AppPreference::query()->updateOrCreate(
            ['key' => self::ChallengeOrderKeyPrefix.$theme],
            ['value' => $fingerprint],
        );
    }

    public function applyLanguage(): void
    {
        $this->translator->setLocale($this->language());
    }

    public function applyAppearance(): void
    {
        NativeAppearance::set($this->appearance());
    }

    private function value(string $key, string $default): string
    {
        return AppPreference::query()->firstOrCreate(
            ['key' => $key],
            ['value' => $default],
        )->value;
    }

    /** @param list<string> $allowedValues */
    private function store(string $key, string $value, array $allowedValues): void
    {
        if (! in_array($value, $allowedValues, true)) {
            throw new InvalidArgumentException("Invalid preference [{$key}].");
        }

        AppPreference::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
