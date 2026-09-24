<?php

namespace App\Http\Requests;

use App\Enums\CostCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi create/update perjalanan dinas (PRD §11: Form Request).
 *
 * Aturan bersama dipakai Store & Update — didefinisikan sekali di sini
 * agar tidak duplikasi dan mudah diuji. Flight BOLEH di dinas
 * (berbeda dengan cuti yang menolak flight).
 */
abstract class BaseTravelRequestRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public static function sharedRules(): array
    {
        return [
            // Opsional: buat untuk karyawan lain (admin/HRGA). Ownership dicek server.
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'purpose' => ['required', 'string', 'max:5000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'is_project_trip' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.category' => ['required', 'string', Rule::in(CostCategory::values())],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.transaction_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:end_date'],
            'items.*.origin' => ['nullable', 'string', 'max:255'],
            'items.*.destination' => ['nullable', 'string', 'max:255'],
            // Batas overflow selaras DB decimal(12,2)/(15,2) + amount(15,2):
            // qty max 9999, price max 9999999999, produk dibatasi after-validator.
            'items.*.quantity' => ['required', 'decimal:0,2', 'min:0', 'max:9999'],
            'items.*.unit_price' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'items.*.metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * After-validator server-side:
     * - Tolak qty*price > 999999999999.99 (batas amount decimal 15,2).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $data = $validator->getData();

            $items = $data['items'] ?? null;
            if (is_array($items)) {
                foreach ($items as $i => $row) {
                    if (! is_array($row)) {
                        continue;
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
                    $product = bcmul((string) $qty, (string) $price, 6);

                    if (function_exists('bccomp') ? bccomp($product, '999999999999.99', 2) === 1 : ((float) $product > 999999999999.99)) {
                        $validator->errors()->add(
                            "items.{$i}.quantity",
                            'Hasil quantity x harga satuan melebihi batas 999.999.999.999,99.'
                        );
                    }
                }

                $allowed = [
                    'flight' => ['airline', 'ticket_number', 'flight_destination', 'departure_time'],
                    'hotel' => ['nights', 'check_in_date', 'check_out_date'],
                    'other' => ['note'],
                    'land_transport' => ['note'],
                    'meal' => ['note'],
                ];
                foreach ($items as $i => $row) {
                    $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
                    if (count($metadata) > 4) {
                        $validator->errors()->add("items.{$i}.metadata", 'Metadata maksimal 4 kunci.');
                    }
                    $category = (string) ($row['category'] ?? '');
                    if ($category === 'hotel') {
                        $checkIn = $metadata['check_in_date'] ?? null;
                        $checkOut = $metadata['check_out_date'] ?? null;
                        foreach (['check_in_date' => $checkIn, 'check_out_date' => $checkOut] as $dateKey => $dateValue) {
                            if (filled($dateValue)) {
                                $parts = is_string($dateValue) ? explode('-', $dateValue) : [];
                                $validDate = count($parts) === 3
                                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)
                                    && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
                                if (! $validDate) {
                                    $validator->errors()->add("items.{$i}.metadata", 'Tanggal check-in/check-out hotel tidak valid.');
                                }
                            }
                        }
                        if ((filled($checkIn) && ! filled($checkOut)) || (! filled($checkIn) && filled($checkOut))) {
                            $validator->errors()->add("items.{$i}.metadata", 'Tanggal check-in dan check-out hotel harus diisi berpasangan.');
                        } elseif (filled($checkIn) && filled($checkOut) && $checkOut <= $checkIn) {
                            $validator->errors()->add("items.{$i}.metadata", 'Tanggal check-out hotel harus setelah check-in.');
                        }
                    }
                    if ($category === 'flight' && filled($metadata['departure_time'] ?? null)
                        && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $metadata['departure_time'])) {
                        $validator->errors()->add("items.{$i}.metadata", 'Jam keberangkatan flight tidak valid.');
                    }
                    foreach ($metadata as $key => $value) {
                        if (! in_array($key, $allowed[$category] ?? [], true) || (! is_string($value) && ! is_numeric($value)) || strlen((string) $value) > 255) {
                            $validator->errors()->add("items.{$i}.metadata", 'Metadata tidak valid untuk kategori item.');
                            break;
                        }
                    }
                    if ($metadata !== [] && strlen((string) json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) > 4096) {
                        $validator->errors()->add("items.{$i}.metadata", 'Metadata maksimal 4096 byte.');
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
            'items.required' => 'Minimal satu item biaya wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal selesai harus >= tanggal mulai.',
            'items.*.category.in' => 'Kategori biaya tidak valid.',
            'items.*.quantity.max' => 'Quantity maksimal 9.999.',
            'items.*.unit_price.max' => 'Harga satuan maksimal 9.999.999.999.',
        ];
    }
}
