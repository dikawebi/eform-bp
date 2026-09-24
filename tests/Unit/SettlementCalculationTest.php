<?php

namespace Tests\Unit;

use App\Enums\DifferenceType;
use App\Models\Employee;
use App\Models\Settlement;
use App\Services\Settlement\CalculateSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettlementCalculationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('differenceCases')]
    public function test_reconciliation_uses_item_sum_and_signed_difference(string $advance, array $items, string $difference, DifferenceType $type): void
    {
        $employee = Employee::factory()->create();
        $settlement = Settlement::forceCreate([
            'settlement_number' => 'STL-202609-0001', 'source_type' => 'travel_request', 'source_id' => 1,
            'employee_id' => $employee->id, 'advance_amount' => $advance, 'actual_amount' => '999999', 'difference_amount' => '999999', 'status' => 'draft',
        ]);
        foreach ($items as $amount) {
            $item = $settlement->items()->create(['transaction_date' => '2026-09-23', 'description' => 'Item', 'category' => 'other']);
            $item->forceFill(['amount' => $amount])->save();
        }
        $result = CalculateSettlement::run($settlement->load('items'));
        $this->assertSame($difference, $result['difference_amount']);
        $this->assertSame($type, $result['difference_type']);
        $this->assertSame('999999', (string) $settlement->getRawOriginal('actual_amount'));
    }

    public static function differenceCases(): array
    {
        return [
            'overpayment' => ['100.00', ['30.00', '20.00'], '50.00', DifferenceType::Overpayment],
            'underpayment' => ['100.00', ['80.00', '40.00'], '-20.00', DifferenceType::Underpayment],
            'balanced' => ['100.00', ['100.00'], '0.00', DifferenceType::Balanced],
        ];
    }
}
