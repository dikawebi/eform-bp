<?php

namespace Database\Seeders;

use App\Models\ItSpecTemplate;
use Illuminate\Database\Seeder;

class ItSpecTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'laptop_office_standard',
                'name' => 'Laptop Standard Office',
                'device_type' => 'laptop',
                'sort_order' => 10,
                'specs' => [
                    ['label' => 'Prosesor', 'value' => 'Intel Core i5 / AMD Ryzen 5 generasi terbaru atau setara'],
                    ['label' => 'RAM', 'value' => '16 GB'],
                    ['label' => 'Penyimpanan', 'value' => '512 GB NVMe SSD'],
                    ['label' => 'Layar', 'value' => '14 inci Full HD'],
                    ['label' => 'Grafis', 'value' => 'GPU terintegrasi'],
                    ['label' => 'Sistem operasi', 'value' => 'Windows 11 Pro'],
                    ['label' => 'Konektivitas', 'value' => 'Wi-Fi 6, Bluetooth 5, USB-C, HDMI'],
                    ['label' => 'Garansi', 'value' => '3 tahun onsite'],
                ],
            ],
            [
                'code' => 'laptop_high_spec',
                'name' => 'Laptop High Spec',
                'device_type' => 'laptop',
                'sort_order' => 20,
                'specs' => [
                    ['label' => 'Prosesor', 'value' => 'Intel Core i7 / AMD Ryzen 7 generasi terbaru atau setara'],
                    ['label' => 'RAM', 'value' => '32 GB'],
                    ['label' => 'Penyimpanan', 'value' => '1 TB NVMe SSD'],
                    ['label' => 'Layar', 'value' => '15.6 inci Full HD'],
                    ['label' => 'Grafis', 'value' => 'GPU diskrit minimal 4 GB'],
                    ['label' => 'Sistem operasi', 'value' => 'Windows 11 Pro'],
                    ['label' => 'Konektivitas', 'value' => 'Wi-Fi 6E, Bluetooth 5.2, USB-C/Thunderbolt, HDMI'],
                    ['label' => 'Garansi', 'value' => '3 tahun onsite'],
                ],
            ],
            [
                'code' => 'desktop_office_standard',
                'name' => 'Desktop Standard Office',
                'device_type' => 'desktop',
                'sort_order' => 30,
                'specs' => [
                    ['label' => 'Prosesor', 'value' => 'Intel Core i5 / AMD Ryzen 5 generasi terbaru atau setara'],
                    ['label' => 'RAM', 'value' => '16 GB'],
                    ['label' => 'Penyimpanan', 'value' => '512 GB NVMe SSD + 1 TB HDD'],
                    ['label' => 'Grafis', 'value' => 'GPU terintegrasi'],
                    ['label' => 'Monitor', 'value' => '23.8 inci Full HD'],
                    ['label' => 'Sistem operasi', 'value' => 'Windows 11 Pro'],
                    ['label' => 'Jaringan', 'value' => 'Gigabit Ethernet dan Wi-Fi 6'],
                    ['label' => 'Garansi', 'value' => '3 tahun onsite'],
                ],
            ],
            [
                'code' => 'desktop_high_spec',
                'name' => 'Desktop High Spec',
                'device_type' => 'desktop',
                'sort_order' => 40,
                'specs' => [
                    ['label' => 'Prosesor', 'value' => 'Intel Core i7 / AMD Ryzen 7 generasi terbaru atau setara'],
                    ['label' => 'RAM', 'value' => '32 GB'],
                    ['label' => 'Penyimpanan', 'value' => '1 TB NVMe SSD'],
                    ['label' => 'Grafis', 'value' => 'GPU diskrit minimal 6 GB'],
                    ['label' => 'Monitor', 'value' => '27 inci QHD'],
                    ['label' => 'Sistem operasi', 'value' => 'Windows 11 Pro'],
                    ['label' => 'Jaringan', 'value' => 'Gigabit Ethernet dan Wi-Fi 6'],
                    ['label' => 'Garansi', 'value' => '3 tahun onsite'],
                ],
            ],
            [
                'code' => 'upgrade_assessment',
                'name' => 'Upgrade Berdasarkan Assessment IT',
                'device_type' => 'upgrade',
                'sort_order' => 50,
                'specs' => [
                    ['label' => 'Diagnosis perangkat', 'value' => 'Ditentukan IT berdasarkan hasil pemeriksaan'],
                    ['label' => 'Komponen yang ditingkatkan', 'value' => 'Mengikuti rekomendasi IT'],
                    ['label' => 'Kompatibilitas', 'value' => 'Wajib diverifikasi sebelum pengadaan'],
                    ['label' => 'Garansi komponen', 'value' => 'Sesuai ketentuan vendor'],
                ],
            ],
        ];

        foreach ($templates as $template) {
            ItSpecTemplate::firstOrCreate(
                ['code' => $template['code']],
                [
                    'name' => $template['name'],
                    'device_type' => $template['device_type'],
                    'specs_json' => $template['specs'],
                    'is_active' => true,
                    'sort_order' => $template['sort_order'],
                ],
            );
        }
    }
}
