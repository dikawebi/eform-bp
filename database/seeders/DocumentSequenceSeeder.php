<?php

namespace Database\Seeders;

use App\Services\DocumentNumberSequence;
use Illuminate\Database\Seeder;

class DocumentSequenceSeeder extends Seeder
{
    public function run(): void
    {
        DocumentNumberSequence::ensureDefaults();
    }
}
