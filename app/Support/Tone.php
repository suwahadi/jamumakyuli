<?php

declare(strict_types=1);

namespace App\Support;

class Tone
{
    public static function chip(string $color, bool $strong = false): string
    {
        return match ($color) {
            'amber' => $strong
                ? 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400'
                : 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
            'indigo' => $strong
                ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400'
                : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
            'emerald' => $strong
                ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400'
                : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
            'sky' => $strong
                ? 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400'
                : 'bg-sky-50 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400',
            'teal' => $strong
                ? 'bg-teal-100 text-teal-600 dark:bg-teal-500/15 dark:text-teal-400'
                : 'bg-teal-50 text-teal-600 dark:bg-teal-500/15 dark:text-teal-400',
            'rose' => $strong
                ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400'
                : 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400',
            default => $strong
                ? 'bg-gray-100 text-gray-600 dark:bg-zinc-700 dark:text-zinc-300'
                : 'bg-gray-100 text-gray-500 dark:bg-zinc-800 dark:text-zinc-500',
        };
    }

    public static function dot(string $color): string
    {
        return match ($color) {
            'amber' => 'bg-amber-500',
            'indigo' => 'bg-indigo-500',
            'emerald' => 'bg-emerald-500',
            'sky' => 'bg-sky-500',
            'teal' => 'bg-teal-500',
            'rose' => 'bg-rose-500',
            default => 'bg-gray-400 dark:bg-zinc-600',
        };
    }
}
