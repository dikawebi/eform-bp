<?php

namespace Database\Seeders;

use App\Models\ItItemOption;
use Illuminate\Database\Seeder;

class ItItemOptionSeeder extends Seeder
{
    public function run(): void
    {
        $devices = [
            ['laptop', 'Laptop'],
            ['desktop', 'Desktop'],
            ['workstation_cad', 'Workstation / CAD'],
            ['upgrade', 'Upgrade perangkat'],
        ];
        foreach ($devices as $order => [$code, $label]) {
            ItItemOption::firstOrCreate(['code' => $code], [
                'label' => $label, 'kind' => 'device',
                'requires_note' => false, 'sort_order' => $order, 'is_active' => true,
            ]);
        }

        $accessories = [
            'Monitor 20 inci', 'External hard drive', 'Digital camera',
            'Printer B/W laser', 'Printer colour laser', 'Printer A4 inkjet',
            'Printer plotter', 'GPS unit', 'Rig/mobile radio', 'Handheld radio',
            'UPS/stabilizer', 'Wireless keyboard/mouse', 'Notebook battery',
            'Notebook power adapter',
        ];
        foreach ($accessories as $order => $label) {
            $code = 'acc_' . ($order + 1);
            ItItemOption::firstOrCreate(['code' => $code], [
                'label' => $label, 'kind' => 'accessory',
                'requires_note' => false, 'sort_order' => 100 + $order, 'is_active' => true,
            ]);
        }

        ItItemOption::firstOrCreate(['code' => 'other'], [
            'label' => 'Lainnya', 'kind' => 'accessory',
            'requires_note' => true, 'sort_order' => 900, 'is_active' => true,
        ]);
    }
}
