<?php

namespace App\Application\AlarmAnalytics;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class WeeklyProgressImage
{
    /**
     * @param  array{on_time_mornings: int, resolved_count: int, on_time_count: int, late_count: int, missed_count: int, snooze_count: int, hardest_weekday: ?int, recommendation: string}  $summary
     */
    public function create(array $summary, int $goal, int $currentStreak, int $bestStreak): string
    {
        $directory = 'weekly-progress-shares';
        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);
        $fileName = $directory.'/'.Str::uuid().'.svg';
        $disk->put($fileName, $this->svg($summary, $goal, $currentStreak, $bestStreak));

        return $disk->path($fileName);
    }

    /**
     * @param  array{on_time_mornings: int, resolved_count: int, on_time_count: int, late_count: int, missed_count: int, snooze_count: int, hardest_weekday: ?int, recommendation: string}  $summary
     */
    private function svg(array $summary, int $goal, int $currentStreak, int $bestStreak): string
    {
        $onTime = $summary['on_time_mornings'];
        $progress = $goal > 0 ? min(1, $onTime / $goal) : 0;
        $barWidth = (int) round(700 * $progress);
        $primary = $this->color((string) config('native-ui.theme.light.primary'), '#1D4ED8');
        $text = $this->color((string) config('native-ui.theme.light.on-surface'), '#0F172A');
        $surface = $this->color((string) config('native-ui.theme.light.surface'), '#FFFFFF');
        $empty = $summary['resolved_count'] === 0
            ? '<text x="80" y="520" font-size="34">'.e(__('app.weekly_share_empty')).'</text>'
            : '<text x="80" y="520" font-size="30">'.e(__('app.weekly_share_metrics', [
                'rate' => $summary['resolved_count'] === 0 ? 0 : (int) round(($summary['on_time_count'] / $summary['resolved_count']) * 100),
                'snoozes' => $summary['snooze_count'],
                'failures' => $summary['late_count'] + $summary['missed_count'],
            ])).'</text>';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 1080 1350">
  <rect width="1080" height="1350" rx="56" fill="{$surface}"/>
  <rect x="0" y="0" width="1080" height="300" rx="56" fill="{$primary}"/>
  <text x="80" y="110" fill="#FFFFFF" font-family="sans-serif" font-size="34">Despertá</text>
  <text x="80" y="205" fill="#FFFFFF" font-family="sans-serif" font-size="58" font-weight="700">{$this->escape(__('app.weekly_share_title'))}</text>
  <text x="80" y="405" fill="{$text}" font-family="sans-serif" font-size="84" font-weight="700">{$onTime} / {$goal}</text>
  <text x="80" y="460" fill="{$text}" font-family="sans-serif" font-size="30">{$this->escape(__('app.weekly_share_goal'))}</text>
  <rect x="80" y="490" width="700" height="24" rx="12" fill="#CBD5E1"/>
  <rect x="80" y="490" width="{$barWidth}" height="24" rx="12" fill="{$primary}"/>
  {$empty}
  <text x="80" y="690" fill="{$text}" font-family="sans-serif" font-size="34">{$this->escape(__('app.current_streak'))}: {$currentStreak} {$this->escape(__('app.days'))}</text>
  <text x="80" y="760" fill="{$text}" font-family="sans-serif" font-size="34">{$this->escape(__('app.best_streak'))}: {$bestStreak} {$this->escape(__('app.days'))}</text>
  <text x="80" y="1240" fill="{$text}" font-family="sans-serif" font-size="24">{$this->escape(__('app.weekly_share_footer'))}</text>
</svg>
SVG;
    }

    private function color(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1 ? $value : $fallback;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
