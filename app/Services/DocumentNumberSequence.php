<?php

namespace App\Services;

use App\Models\DocumentSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class DocumentNumberSequence
{
    public static function generate(string $module): string
    {
        self::ensureDefaults();

        return DB::transaction(function () use ($module): string {
            $sequence = DocumentSequence::query()
                ->where('module', $module)
                ->lockForUpdate()
                ->firstOrFail();

            $period = $sequence->period();

            if ($sequence->last_period !== $period) {
                $sequence->last_period = $period;
                $sequence->next_number = 1;
            }

            $number = max(1, (int) $sequence->next_number);
            $formatted = $sequence->formatNumber($number, $period);

            $sequence->next_number = $number + 1;
            $sequence->save();

            return $formatted;
        });
    }

    public static function ensureDefaults(): void
    {
        DB::transaction(function (): void {
            foreach (array_keys(DocumentSequence::MODULES) as $module) {
                DocumentSequence::query()->firstOrCreate(
                    ['module' => $module],
                    self::initialAttributes($module),
                );
            }
        });
    }

    public static function isNumberUsed(string $module, string $number): bool
    {
        $meta = DocumentSequence::MODULES[$module] ?? null;

        if ($meta === null) {
            return false;
        }

        /** @var class-string<Model> $model */
        $model = $meta['model'];

        return $model::query()->where($meta['column'], $number)->exists();
    }

    /**
     * @return array{module: string, prefix: string, separator: string, period_format: string, padding: int, next_number: int, last_period: string|null}
     */
    private static function initialAttributes(string $module): array
    {
        $attributes = DocumentSequence::defaultAttributes($module);
        $period = $attributes['period_format'] !== ''
            ? now()->format($attributes['period_format'])
            : null;

        $attributes['last_period'] = $period;
        $attributes['next_number'] = self::nextFromExisting(
            $module,
            $attributes['prefix'],
            $attributes['separator'],
            $period,
        );

        return $attributes;
    }

    private static function nextFromExisting(string $module, string $prefix, string $separator, ?string $period): int
    {
        $meta = DocumentSequence::MODULES[$module];
        /** @var class-string<Model> $model */
        $model = $meta['model'];

        $leading = trim($prefix);
        if ($period !== null && $period !== '') {
            $leading = $leading !== ''
                ? $leading.$separator.$period
                : $period;
        }

        if ($leading === '') {
            return 1;
        }

        $last = $model::query()
            ->where($meta['column'], 'like', $leading.'%')
            ->orderByDesc($meta['column'])
            ->value($meta['column']);

        if ($last !== null && preg_match('/(\d+)$/', (string) $last, $matches) === 1) {
            return ((int) $matches[1]) + 1;
        }

        return 1;
    }
}
