<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    /**
     * @var array<string, array{label: string, prefix: string, period_format: string, padding: int, model: class-string<Model>, column: string}>
     */
    public const MODULES = [
        'leave_request' => [
            'label' => 'Cuti/Izin',
            'prefix' => 'CUTI',
            'period_format' => 'Ym',
            'padding' => 4,
            'model' => LeaveRequest::class,
            'column' => 'request_number',
        ],
        'travel_request' => [
            'label' => 'Perjalanan Dinas',
            'prefix' => 'DINAS',
            'period_format' => 'Ym',
            'padding' => 4,
            'model' => TravelRequest::class,
            'column' => 'request_number',
        ],
        'settlement' => [
            'label' => 'Settlement',
            'prefix' => 'STL',
            'period_format' => 'Ym',
            'padding' => 4,
            'model' => Settlement::class,
            'column' => 'settlement_number',
        ],
        'medical_claim' => [
            'label' => 'Medical Claim',
            'prefix' => 'MED',
            'period_format' => 'Ym',
            'padding' => 4,
            'model' => MedicalClaim::class,
            'column' => 'claim_number',
        ],
        'it_request' => [
            'label' => 'IT Request',
            'prefix' => 'ITR',
            'period_format' => 'Ym',
            'padding' => 4,
            'model' => ItRequest::class,
            'column' => 'request_number',
        ],
        'erp_request' => [
            'label' => 'ERP Request',
            'prefix' => 'ERP',
            'period_format' => 'Ym',
            'padding' => 4,
            'model' => ErpRequest::class,
            'column' => 'request_number',
        ],
    ];

    protected $fillable = [
        'module',
        'prefix',
        'separator',
        'period_format',
        'padding',
        'next_number',
        'last_period',
    ];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'next_number' => 'integer',
        ];
    }

    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function period(?CarbonInterface $date = null): ?string
    {
        $format = trim((string) $this->period_format);

        return $format === '' ? null : ($date ?? now())->format($format);
    }

    public function formatNumber(int $number, ?string $period = null): string
    {
        $separator = $this->separator !== '' ? $this->separator : '-';
        $parts = [];

        if (trim((string) $this->prefix) !== '') {
            $parts[] = $this->prefix;
        }

        if ($period !== null && $period !== '') {
            $parts[] = $period;
        }

        $parts[] = str_pad((string) $number, max(1, (int) $this->padding), '0', STR_PAD_LEFT);

        return implode($separator, $parts);
    }

    public function nextNumberForPeriod(?CarbonInterface $date = null): int
    {
        return $this->last_period === $this->period($date)
            ? max(1, (int) $this->next_number)
            : 1;
    }

    public function preview(?CarbonInterface $date = null): string
    {
        return $this->formatNumber($this->nextNumberForPeriod($date), $this->period($date));
    }

    /**
     * @return array{module: string, prefix: string, separator: string, period_format: string, padding: int, next_number: int, last_period: string|null}
     */
    public static function defaultAttributes(string $module): array
    {
        $meta = self::MODULES[$module] ?? null;

        if ($meta === null) {
            throw new \InvalidArgumentException("Modul sequence tidak dikenal: {$module}.");
        }

        return [
            'module' => $module,
            'prefix' => $meta['prefix'],
            'separator' => '-',
            'period_format' => $meta['period_format'],
            'padding' => $meta['padding'],
            'next_number' => 1,
            'last_period' => null,
        ];
    }

    public function moduleLabel(): string
    {
        return (string) (self::MODULES[$this->module]['label'] ?? $this->module);
    }

    public function numberModel(): ?Model
    {
        $model = self::MODULES[$this->module]['model'] ?? null;

        return $model !== null ? new $model : null;
    }
}
