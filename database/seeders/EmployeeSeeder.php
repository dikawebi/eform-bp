<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class EmployeeSeeder extends Seeder
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    public function run(): void
    {
        $path = (string) config('eform.employee_workbook_path', '');
        if ($path === '' || ! is_file($path)) {
            throw new RuntimeException('Set EFORM_EMPLOYEE_WORKBOOK_PATH ke lokasi FORM CUTI DINAS FINAL.xlsx sebelum menjalankan EmployeeSeeder.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk membaca workbook Excel.');
        }

        $workbookRows = $this->readDataSheet($path);
        if ($workbookRows === []) {
            throw new RuntimeException('Sheet DATA tidak berisi baris karyawan yang valid. Tidak ada perubahan database.');
        }

        $created = 0;
        $updated = 0;
        DB::transaction(function () use ($workbookRows, &$created, &$updated): void {
            foreach ($workbookRows as $profile) {
                $employee = Employee::query()->firstOrNew(['employee_number' => $profile['employee_number']]);
                $isNew = ! $employee->exists;
                $employee->fill($profile);

                // Source workbook has no account, supervisor/HOD, active, or
                // employment-status mapping. Only new master rows get safe defaults;
                // existing operational links/status are deliberately preserved.
                if ($isNew) {
                    $employee->active = true;
                    $employee->employment_status = 'permanent';
                    $employee->is_project_based = false;
                    $employee->user_id = null;
                    $employee->supervisor_id = null;
                    $employee->hod_id = null;
                    $employee->created_by = null;
                }
                $employee->save();

                $isNew ? $created++ : $updated++;
            }
        });

        $this->command?->info("Employee master seed selesai: {$created} baru, {$updated} diperbarui.");
        $this->command?->warn('Akun login dan relasi Supervisor/HOD tidak tersedia di sheet DATA; field tersebut tidak dibuat oleh seeder ini.');
    }

    /** @return list<array<string, mixed>> */
    private function readDataSheet(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Workbook Excel tidak dapat dibuka.');
        }

        try {
            $workbook = $this->xml($zip->getFromName('xl/workbook.xml'), 'workbook.xml');
            $relationships = $this->xml($zip->getFromName('xl/_rels/workbook.xml.rels'), 'workbook relationships');
            $relationshipId = null;
            $workbook->registerXPathNamespace('m', self::MAIN_NS);
            foreach ($workbook->xpath('//m:sheets/m:sheet') ?: [] as $sheet) {
                if ((string) $sheet->attributes()['name'] === 'DATA') {
                    $relationshipId = (string) $sheet->attributes(self::REL_NS)['id'];
                    break;
                }
            }
            if ($relationshipId === null) {
                throw new RuntimeException('Sheet DATA tidak ditemukan dalam workbook.');
            }

            $target = null;
            foreach ($relationships->Relationship as $relationship) {
                if ((string) $relationship->attributes()['Id'] === $relationshipId) {
                    $target = 'xl/'.ltrim((string) $relationship->attributes()['Target'], '/');
                    break;
                }
            }
            if ($target === null || ! str_starts_with($target, 'xl/worksheets/')) {
                throw new RuntimeException('Lokasi worksheet DATA tidak valid.');
            }

            $sharedStrings = $this->readSharedStrings($zip->getFromName('xl/sharedStrings.xml'));
            $sheet = $this->xml($zip->getFromName($target), 'sheet DATA');
            $rows = [];
            $seenNumbers = [];
            $rowIndexes = [];
            $unresolvedConflicts = [];
            $dataIssues = [];
            $header = [];

            foreach ($sheet->children(self::MAIN_NS)->sheetData->children(self::MAIN_NS)->row as $row) {
                $rowNumber = (int) $row->attributes()['r'];
                $cells = [];
                foreach ($row->children(self::MAIN_NS)->c as $cell) {
                    $address = (string) $cell->attributes()['r'];
                    preg_match('/^[A-Z]+/', $address, $columnMatch);
                    $column = $columnMatch[0] ?? '';
                    $value = $this->cellValue($cell, $sharedStrings);
                    if ($column !== '') {
                        $cells[$column] = $value;
                    }
                }

                if ($rowNumber === 3) {
                    foreach ($cells as $column => $label) {
                        $header[$this->normalizeHeader((string) $label)] = $column;
                    }
                    continue;
                }
                if ($rowNumber < 4) {
                    continue;
                }

                $employeeNumber = $this->normalizeEmployeeNumber((string) ($cells[$header['NIK'] ?? 'B'] ?? ''));
                if ($employeeNumber === '') {
                    continue;
                }
                $name = trim((string) ($cells[$header['NAME'] ?? 'C'] ?? ''));
                $department = trim((string) ($cells[$header['DIVISION'] ?? 'D'] ?? ''));
                $level = trim((string) ($cells[$header['LEVEL'] ?? 'E'] ?? ''));
                $jobTitle = trim((string) ($cells[$header['JOB'] ?? 'F'] ?? ''));
                $poh = mb_strtolower(trim((string) ($cells[$header['POH STATUS'] ?? 'I'] ?? '')));
                $pohIsLocal = in_array($poh, ['lokal', 'local'], true);
                $pohIsNonLocal = in_array($poh, ['non lokal', 'non-local', 'non_local', 'expatriate'], true);

                $profile = [
                    'employee_number' => $employeeNumber,
                    'name' => $name,
                    'department' => $department,
                    'level' => $level,
                    'job_title' => $jobTitle,
                    'roster' => $this->nullableString($cells[$header['ROSTER'] ?? 'G'] ?? null),
                    'poh_status' => $pohIsLocal ? 'local' : ($pohIsNonLocal ? 'non_local' : null),
                    'poh_city' => $this->nullableString($cells[$header['KAB'] ?? 'J'] ?? null),
                    'poh_province' => $this->nullableString($cells[$header['PROV'] ?? 'K'] ?? null),
                    'joined_at' => $this->excelDate($cells[$header['DOJ'] ?? 'H'] ?? null),
                ];
                if (isset($seenNumbers[$employeeNumber])) {
                    $previous = $seenNumbers[$employeeNumber]['profile'];
                    foreach ($profile as $key => $value) {
                        if (($value === null || $value === '') && ($previous[$key] ?? null) !== null && ($previous[$key] ?? '') !== '') {
                            $profile[$key] = $previous[$key];
                        }
                    }
                    $rows[$rowIndexes[$employeeNumber]] = $profile;
                    $seenNumbers[$employeeNumber] = ['row' => $rowNumber, 'profile' => $profile];
                } else {
                    $rowIndexes[$employeeNumber] = count($rows);
                    $rows[] = $profile;
                }

                $profile = $seenNumbers[$employeeNumber]['profile'] ?? $profile;
                if (($profile['name'] ?? '') === '' || ($profile['department'] ?? '') === '' || ($profile['level'] ?? '') === '' || ($profile['job_title'] ?? '') === '') {
                    throw new RuntimeException("Profil NIK {$employeeNumber} pada baris {$rowNumber} tidak lengkap (nama/divisi/level/jabatan). Tidak ada perubahan database.");
                }
                if ($profile['poh_status'] === null) {
                    $dataIssues[] = "POH tidak dikenal untuk NIK {$employeeNumber} baris {$rowNumber}: {$poh}";
                }
                $seenNumbers[$employeeNumber] = ['row' => $rowNumber, 'profile' => $profile];
            }

            if ($dataIssues !== []) {
                throw new RuntimeException('Sheet DATA perlu koreksi/mapping sebelum seeding: '.implode(' | ', $dataIssues).'. Tidak ada perubahan database.');
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /** @return list<string> */
    private function readSharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $shared = $this->xml($xml, 'sharedStrings.xml');
        $strings = [];
        foreach ($shared->children(self::MAIN_NS)->si as $item) {
            $strings[] = $this->textNodes($item);
        }

        return $strings;
    }

    private function textNodes(SimpleXMLElement $node): string
    {
        $text = '';
        foreach ($node->children(self::MAIN_NS) as $child) {
            if ($child->getName() === 't') {
                $text .= (string) $child;
            } else {
                $text .= $this->textNodes($child);
            }
        }

        return $text;
    }

    /** @param list<string> $sharedStrings */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) $cell->attributes()['t'];
        $value = (string) $cell->children(self::MAIN_NS)->v;
        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? null;
        }
        if ($type === 'inlineStr') {
            return $this->textNodes($cell->children(self::MAIN_NS)->is);
        }

        return $value === '' ? null : $value;
    }

    private function xml(?string $value, string $label): SimpleXMLElement
    {
        if ($value === null || trim($value) === '') {
            throw new RuntimeException("Bagian {$label} tidak ditemukan atau kosong.");
        }
        $xml = simplexml_load_string($value);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException("Bagian {$label} tidak dapat dibaca.");
        }

        return $xml;
    }

    private function normalizeHeader(string $header): string
    {
        return mb_strtoupper(trim(str_replace('.', '', $header)));
    }

    private function normalizeEmployeeNumber(string $value): string
    {
        $value = trim($value);
        if (is_numeric($value) && (float) $value === (float) (int) $value) {
            return (string) (int) $value;
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function excelDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            $date = date_create_immutable((string) $value);

            return $date?->format('Y-m-d');
        }

        $days = (int) floor((float) $value);

        return (new \DateTimeImmutable('1899-12-30'))->modify("+{$days} days")->format('Y-m-d');
    }
}
