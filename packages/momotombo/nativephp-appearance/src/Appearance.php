<?php

namespace Momotombo\NativephpAppearance;

/**
 * Application-local appearance bridge for NativePHP Mobile.
 *
 * The service validates the public PHP contract before forwarding it to the
 * platform bridge. It intentionally returns no fallback mode outside a native
 * runtime so callers can distinguish an unavailable bridge from `system`.
 */
class Appearance
{
    /**
     * Persist and apply an application appearance mode.
     *
     * @param  'system'|'light'|'dark'  $mode
     *
     * @throws \InvalidArgumentException When the mode is not supported.
     */
    public function set(string $mode): void
    {
        if (! in_array($mode, ['system', 'light', 'dark'], true)) {
            throw new \InvalidArgumentException('Appearance must be system, light, or dark.');
        }

        if (function_exists('nativephp_call')) {
            nativephp_call('Appearance.Set', json_encode(['mode' => $mode], JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Return the appearance last persisted by the native platform.
     *
     * @return 'system'|'light'|'dark'|null Null when the NativePHP bridge is unavailable or has no mode.
     */
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
