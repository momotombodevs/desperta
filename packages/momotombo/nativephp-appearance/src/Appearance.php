<?php

namespace Momotombo\NativephpAppearance;

class Appearance
{
    public function set(string $mode): void
    {
        if (! in_array($mode, ['system', 'light', 'dark'], true)) {
            throw new \InvalidArgumentException('Appearance must be system, light, or dark.');
        }

        if (function_exists('nativephp_call')) {
            nativephp_call('Appearance.Set', json_encode(['mode' => $mode], JSON_THROW_ON_ERROR));
        }
    }

    public function get(): ?string
    {
        if (function_exists('nativephp_call')) {
            $result = nativephp_call('Appearance.Get', '{}');

            if ($result) {
                $decoded = json_decode($result, true);

                return $decoded['mode'] ?? null;
            }
        }

        return null;
    }
}
