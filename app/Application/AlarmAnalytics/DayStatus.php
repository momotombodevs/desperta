<?php

namespace App\Application\AlarmAnalytics;

/** The outcome of a day's alarms, with the theme colors every habits view shares. */
enum DayStatus: string
{
    case OnTime = 'on_time';
    case Late = 'late';
    case Missed = 'missed';
    case Pending = 'pending';
    case None = 'none';

    /** Theme token for this outcome, for chart series and other data-driven color. */
    public function themeToken(): string
    {
        return match ($this) {
            self::OnTime => 'success',
            self::Late => 'warning',
            self::Missed => 'destructive',
            self::Pending => 'secondary',
            self::None => 'surface-variant',
        };
    }

    public function dotClass(): string
    {
        return $this === self::None ? 'text-theme-outline' : 'text-theme-'.$this->themeToken();
    }

    public function cellClass(): string
    {
        return 'bg-theme-'.$this->themeToken();
    }

    public function cellTextClass(): string
    {
        return $this === self::None ? 'text-theme-on-surface-variant' : 'text-theme-on-'.$this->themeToken();
    }
}
