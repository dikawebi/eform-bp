<?php

namespace App\Http\Requests;

use App\Enums\LeavePeriodCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi create cuti/izin (PRD §11: Form Request).
 *
 * Aturan bersama dipakai Store & Update — didefinisikan sekali di sini
 * agar tidak duplikasi dan mudah diuji.
 */
abstract class BaseLeaveRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function sharedRules(): array
    {
        return [
            // Opsional: buat untuk karyawan lain (admin/HRGA). Ownership dicek server.
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'leave_type' => ['required', 'string', 'max:50', Rule::in(config('eform.leave_types', []))],
            'reason' => ['required', 'string', 'max:5000'],
            'last_working_date' => ['nullable', 'date_format:Y-m-d'],
            'onsite_date' => ['nullable', 'date_format:Y-m-d'],
            'periods' => ['required', 'array', 'min:1', 'max:31'],
            'periods.*.category' => ['required', 'string', Rule::in(LeavePeriodCategory::values())],
            'periods.*.start_date' => ['required', 'date_format:Y-m-d'],
            'periods.*.end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:periods.*.start_date'],
            'periods.*.notes' => ['nullable', 'string', 'max:1000'],
            // 'flight' SENGAJA tidak masuk daftar: ditolak untuk cuti.
            'cost_items' => ['nullable', 'array', 'max:50'],
            'cost_items.*.category' => ['required', 'string', Rule::in(config('eform.leave_cost_categories', []))],
            'cost_items.*.description' => ['required', 'string', 'max:255'],
            // Batas overflow selaras DB decimal(12,2)/(15,2) + amount(15,2):
            // qty max 9999, price max 9999999999, produk dibatasi after-validator.
            'cost_items.*.quantity' => ['required', 'numeric', 'min:0', 'max:9999'],
            'cost_items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'cost_items.*.origin' => ['nullable', 'string', 'max:255'],
            'cost_items.*.destination' => ['nullable', 'string', 'max:255'],
            'cost_items.*.flight_destination' => ['nullable', 'string', 'max:255'],
            'cost_items.*.service_date' => ['nullable', 'date_format:Y-m-d'],
            'cost_items.*.check_in_date' => ['nullable', 'date_format:Y-m-d'],
            'cost_items.*.check_out_date' => ['nullable', 'date_format:Y-m-d'],
            'cost_items.*.departure_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * After-validator server-side:
     * - A4: tolak periode tumpang-tindih (sort by start, next.start <= prev.end).
     * - A3: tolak qty*price > 999999999999.99 (batas amount decimal 15,2).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $data = $validator->getData();

            // --- Overlap periode ---
            $periods = $data['periods'] ?? null;
            if (is_array($periods) && count($periods) > 1) {
                $ranges = [];
                foreach ($periods as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $start = $row['start_date'] ?? null;
                    $end = $row['end_date'] ?? null;
                    if (! is_string($start) || ! is_string($end)) {
                        continue;
                    }
                    // Hanya cek format valid; validasi format ditangani rules.
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                        continue;
                    }
                    if ($end < $start) {
                        continue;
                    }
                    $ranges[] = ['start' => $start, 'end' => $end];
                }

                if (count($ranges) > 1) {
                    usort($ranges, fn ($a, $b) => strcmp($a['start'], $b['start']));

                    $prevEnd = $ranges[0]['end'];
                    foreach (array_slice($ranges, 1) as $range) {
                        if ($range['start'] <= $prevEnd) {
                            $validator->errors()->add(
                                'periods',
                                'Periode cuti tidak boleh tumpang-tindih.'
                            );
                            break;
                        }
                        if ($range['end'] > $prevEnd) {
                            $prevEnd = $range['end'];
                        }
                    }
                }
            }

            // --- Overflow qty * price ---
            $items = $data['cost_items'] ?? null;
            if (is_array($items)) {
                foreach ($items as $i => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    if (($row['category'] ?? null) === 'hotel') {
                        $checkIn = $row['check_in_date'] ?? null;
                        $checkOut = $row['check_out_date'] ?? null;
                        if ((filled($checkIn) && ! filled($checkOut)) || (! filled($checkIn) && filled($checkOut))) {
                            $validator->errors()->add("cost_items.{$i}.check_out_date", 'Tanggal check-in dan check-out harus diisi berpasangan.');
                        } elseif (filled($checkIn) && filled($checkOut) && $checkOut <= $checkIn) {
                            $validator->errors()->add("cost_items.{$i}.check_out_date", 'Tanggal check-out harus setelah check-in.');
                        }
                    }
                    $qty = $row['quantity'] ?? null;
                    $price = $row['unit_price'] ?? null;
                    if (! is_numeric($qty) || ! is_numeric($price)) {
                        continue;
                    }
                    if ((float) $qty < 0 || (float) $price < 0) {
                        continue;
                    }
                    // String math agar presisi (bukan float mentah).
                    $product = function_exists('bcmul')
                        ? bcmul((string) $qty, (string) $price, 6)
                        : number_format(((float) $qty) * ((float) $price), 6, '.', '');

                    if (function_exists('bccomp') ? bccomp($product, '999999999999.99', 2) === 1 : ((float) $product > 999999999999.99)) {
                        $validator->errors()->add(
                            "cost_items.{$i}.quantity",
                            'Hasil quantity x harga satuan melebihi batas 999.999.999.999,99.'
                        );
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periods.required' => 'Minimal satu periode cuti wajib diisi.',
            'periods.*.end_date.after_or_equal' => 'Tanggal selesai periode harus >= tanggal mulai.',
            'periods.*.category.in' => 'Kategori periode tidak valid.',
            'cost_items.*.category.in' => 'Kategori biaya tidak berlaku untuk cuti (tiket pesawat/flight ditolak).',
            'cost_items.*.quantity.max' => 'Quantity maksimal 9.999.',
            'cost_items.*.unit_price.max' => 'Harga satuan maksimal 9.999.999.999.',
            'leave_type.in' => 'Tipe cuti tidak valid.',
        ];
    }
}
