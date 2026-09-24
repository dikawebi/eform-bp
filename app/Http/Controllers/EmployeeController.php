<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Master Karyawan Phase 1 (PRD §4, §6, §7).
 *
 * - Validasi via Form Request.
 * - Authorization via EmployeePolicy di server.
 * - Tanpa hapus fisik: destroy() menonaktifkan via active=false.
 */
class EmployeeController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'in:all,1,0'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $activeFilter = $validated['active'] ?? 'all';

        $employees = Employee::query()
            ->with(['supervisor:id,employee_number,name', 'hod:id,employee_number,name'])
            ->when($search !== '', function ($query) use ($search) {
                $like = "%{$search}%";
                $query->where(function ($inner) use ($like) {
                    $inner->where('employee_number', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('department', 'like', $like);
                });
            })
            ->when($activeFilter !== 'all', function ($query) use ($activeFilter) {
                $query->where('active', $activeFilter === '1');
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Master/Employees/Index', [
            'employees' => $employees,
            'filters' => [
                'search' => $search,
                'active' => $activeFilter,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('Master/Employees/Create', [
            'users' => $this->linkableUsers(),
            'supervisors' => $this->employeeOptions(),
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        try {
            $employee = DB::transaction(function () use ($request) {
                $data = $request->validated();
                // B1: created_by/updated_by di luar mass-assignment (tidak fillable).
                unset($data['created_by'], $data['updated_by']);

                // B2: NIK sudah dinormalisasi trim di Form Request.
                $employee = new Employee($data);
                $employee->created_by = $request->user()->getKey();
                $employee->updated_by = $request->user()->getKey();
                $employee->save();

                return $employee;
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateEntry($e)) {
                throw ValidationException::withMessages([
                    'employee_number' => 'NIK sudah terdaftar.',
                ]);
            }

            throw $e;
        }

        activity()
            ->performedOn($employee)
            ->causedBy($request->user())
            ->withProperties(['employee_number' => $employee->employee_number])
            ->log('employee.created');

        return redirect()
            ->route('master.employees.show', $employee)
            ->with('success', 'Data karyawan berhasil dibuat.');
    }

    public function show(Employee $employee): Response
    {
        $this->authorize('view', $employee);

        $employee->load(['user:id,name,email', 'supervisor', 'hod', 'createdBy:id,name', 'updatedBy:id,name']);

        return Inertia::render('Master/Employees/Show', [
            'employee' => $employee,
        ]);
    }

    public function edit(Employee $employee): Response
    {
        $this->authorize('update', $employee);

        $employee->load(['user:id,name,email']);

        return Inertia::render('Master/Employees/Edit', [
            'employee' => $employee,
            'users' => $this->linkableUsers($employee),
            'supervisors' => $this->employeeOptions($employee),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $oldUserId = $employee->user_id;

        try {
            DB::transaction(function () use ($request, $employee) {
                $data = $request->validated();
                // B1/B5: di luar mass-assignment + active tidak via update umum.
                unset($data['active'], $data['created_by'], $data['updated_by']);

                $employee->fill($data);
                $employee->updated_by = $request->user()->getKey();
                $employee->save();
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateEntry($e)) {
                throw ValidationException::withMessages([
                    'employee_number' => 'NIK sudah terdaftar.',
                ]);
            }

            throw $e;
        }

        $employee->refresh();

        activity()
            ->performedOn($employee)
            ->causedBy($request->user())
            ->withProperties([
                'employee_number' => $employee->employee_number,
                // B6: catat old→new user terhubung.
                'user_id_old' => $oldUserId,
                'user_id_new' => $employee->user_id,
            ])
            ->log('employee.updated');

        return redirect()
            ->route('master.employees.show', $employee)
            ->with('success', 'Data karyawan berhasil diperbarui.');
    }

    /**
     * Deteksi duplicate entry MySQL 1062 / SQLSTATE 23000 (B2 race).
     */
    protected function isDuplicateEntry(QueryException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');
        $message = strtolower($e->getMessage());

        return $code === '1062'
            || str_contains($message, 'duplicate entry');
    }

    /**
     * Nonaktifkan tanpa hapus fisik.
     * B5: tolak jika masih punya bawahan aktif kecuali reassign;
     * set ended_at saat deactivate; B1: updated_by di luar mass-assignment.
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'reassign_to' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $reassignTo = isset($validated['reassign_to']) ? (int) $validated['reassign_to'] : null;

        if ($reassignTo !== null && $reassignTo === (int) $employee->getKey()) {
            return redirect()->back()->withErrors([
                'reassign_to' => 'Reassign tidak boleh ke diri sendiri.',
            ]);
        }

        if ($reassignTo !== null) {
            $target = Employee::query()->find($reassignTo);
            if ($target === null || ! $target->active) {
                return redirect()->back()->withErrors([
                    'reassign_to' => 'Karyawan pengganti harus aktif.',
                ]);
            }
        }

        $activeSubordinates = Employee::query()
            ->where('active', true)
            ->where(function ($query) use ($employee) {
                $query->where('supervisor_id', $employee->getKey())
                    ->orWhere('hod_id', $employee->getKey());
            })
            ->count();

        if ($activeSubordinates > 0 && $reassignTo === null) {
            return redirect()->back()->withErrors([
                'employee' => "Karyawan masih memiliki {$activeSubordinates} bawahan aktif. Nonaktifkan/dipindahkan dulu atau isi reassign_to.",
            ]);
        }

        DB::transaction(function () use ($request, $employee, $reassignTo) {
            if ($reassignTo !== null) {
                Employee::query()
                    ->where('supervisor_id', $employee->getKey())
                    ->update([
                        'supervisor_id' => $reassignTo,
                        'updated_by' => $request->user()->getKey(),
                    ]);
                Employee::query()
                    ->where('hod_id', $employee->getKey())
                    ->update([
                        'hod_id' => $reassignTo,
                        'updated_by' => $request->user()->getKey(),
                    ]);
            }

            // B1: di luar mass-assignment via atribut langsung.
            $employee->active = false;
            if ($employee->ended_at === null) {
                $employee->ended_at = now()->toDateString();
            }
            $employee->updated_by = $request->user()->getKey();
            $employee->save();
        });

        activity()
            ->performedOn($employee)
            ->causedBy($request->user())
            ->withProperties(['employee_number' => $employee->employee_number])
            ->log('employee.deactivated');

        return redirect()
            ->route('master.employees.index')
            ->with('success', 'Karyawan dinonaktifkan (active=false).');
    }

    /**
     * Import CSV master karyawan (B4 two-pass + B1/B2/B3).
     *
     * - Validasi mime csv/txt, maks 5MB, cap 1000 baris.
     * - Strip BOM header, tolak CSV injection (=,+,-,@,TAB,CR).
     * - Validasi ketat panjang + date_format Y-m-d + ended>=joined.
     * - Two-pass agar forward-ref dalam file ter-resolve.
     * - Tolak duplikat NIK (DB maupun intra-file), self-ref, cycle, nonaktif.
     */
    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $validated = $request->validate(
            [
                'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            ],
            [
                'file.mimes' => 'File harus berformat CSV (.csv/.txt).',
                'file.max' => 'Ukuran file maksimal 5MB.',
            ]
        );

        /** @var UploadedFile $file */
        $file = $validated['file'];

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()->back()->withErrors(['file' => 'File tidak dapat dibaca.']);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return redirect()->back()->withErrors(['file' => 'File CSV kosong.']);
        }

        // B4: strip BOM dari sel header pertama.
        if (isset($header[0])) {
            $header[0] = str_replace("\xEF\xBB\xBF", '', (string) $header[0]);
        }
        $header = array_map(fn ($item) => strtolower(trim((string) $item)), $header);

        $required = ['employee_number', 'name', 'department', 'level', 'job_title'];
        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                fclose($handle);

                return redirect()->back()->withErrors([
                    'file' => "Header wajib '{$column}' tidak ditemukan.",
                ]);
            }
        }

        // Kumpulkan semua baris dulu untuk cap 1000 (B4).
        $rawRows = [];
        $lineNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }
            $rawRows[] = ['line' => $lineNumber, 'row' => $row];
        }
        fclose($handle);

        if (count($rawRows) > 1000) {
            return redirect()->back()->withErrors([
                'file' => 'Maksimal 1000 baris data per upload. Pecah file menjadi beberapa bagian.',
            ]);
        }

        $existingMap = Employee::query()->pluck('id', 'employee_number')->all();
        $existingActiveMap = Employee::query()->where('active', true)->pluck('id', 'employee_number')->all();

        $failed = [];
        $candidates = [];
        $seenInFile = [];

        // File NIK set untuk forward-ref (normalisasi trim).
        $fileNikSet = [];
        foreach ($rawRows as $entry) {
            if (count($entry['row']) !== count($header)) {
                continue;
            }
            $combined = array_combine($header, $entry['row']);
            if ($combined === false) {
                continue;
            }
            $nik = trim((string) ($combined['employee_number'] ?? ''));
            if ($nik !== '') {
                $fileNikSet[$nik] = true;
            }
        }

        // File active map (untuk cek atasan nonaktif dalam file).
        $fileActiveMap = [];
        foreach ($rawRows as $entry) {
            if (count($entry['row']) !== count($header)) {
                continue;
            }
            $combined = array_combine($header, $entry['row']);
            if ($combined === false) {
                continue;
            }
            $nik = trim((string) ($combined['employee_number'] ?? ''));
            if ($nik === '') {
                continue;
            }
            $activeRaw = $combined['active'] ?? '1';
            $activeRaw = $activeRaw === '' ? '1' : trim((string) $activeRaw);
            // Jangan timpa jika NIK duplikat: pakai kemunculan pertama.
            if (! array_key_exists($nik, $fileActiveMap)) {
                $fileActiveMap[$nik] = $this->parseBoolean($activeRaw, true);
            }
        }

        foreach ($rawRows as $entry) {
            $entryLine = $entry['line'];
            $row = $entry['row'];

            if (count($row) !== count($header)) {
                $failed[] = [
                    'row' => $entryLine,
                    'employee_number' => null,
                    'errors' => 'Jumlah kolom tidak sesuai header.',
                ];

                continue;
            }

            /** @var array<string, string|null> $record */
            $record = array_combine($header, array_map(
                fn ($value) => $value === '' ? null : trim((string) $value),
                $row
            ));

            $nik = trim((string) ($record['employee_number'] ?? ''));

            if ($nik === '') {
                $failed[] = ['row' => $entryLine, 'employee_number' => null, 'errors' => 'NIK wajib diisi.'];

                continue;
            }
            $record['employee_number'] = $nik;

            // B2: duplikat DB maupun intra-file.
            if (isset($existingMap[$nik]) || isset($seenInFile[$nik])) {
                $failed[] = [
                    'row' => $entryLine,
                    'employee_number' => $nik,
                    'errors' => 'NIK duplikat: sudah terdaftar.',
                ];
                $seenInFile[$nik] = true;

                continue;
            }
            $seenInFile[$nik] = true;

            $rowErrors = $this->validateImportRow($record, $existingMap, $fileNikSet, $existingActiveMap, $fileActiveMap);
            if ($rowErrors !== []) {
                $failed[] = [
                    'row' => $entryLine,
                    'employee_number' => $nik,
                    'errors' => implode(' ', $rowErrors),
                ];

                continue;
            }

            $candidates[] = ['line' => $entryLine, 'record' => $record];
        }

        // B3: tolak cycle/self/descendant via file-graph walk max depth 10.
        $parentMap = [];
        foreach ($candidates as $candidate) {
            $nik = $candidate['record']['employee_number'];
            $supNik = isset($candidate['record']['supervisor_number']) && $candidate['record']['supervisor_number'] !== null
                ? trim((string) $candidate['record']['supervisor_number']) : null;
            $hodNik = isset($candidate['record']['hod_number']) && $candidate['record']['hod_number'] !== null
                ? trim((string) $candidate['record']['hod_number']) : null;
            $parentMap[$nik] = [
                'sup' => ($supNik === '' ? null : $supNik),
                'hod' => ($hodNik === '' ? null : $hodNik),
            ];
        }

        $filtered = [];
        foreach ($candidates as $candidate) {
            $nik = $candidate['record']['employee_number'];
            if ($this->fileWouldCreateCycle($nik, $parentMap)) {
                $failed[] = [
                    'row' => $candidate['line'],
                    'employee_number' => $nik,
                    'errors' => 'Penugasan atasan membentuk cycle hierarki.',
                ];

                continue;
            }
            $filtered[] = $candidate;
        }
        $candidates = $filtered;

        // Pass 1 insert tanpa ref (agar forward-ref ter-resolve).
        $nikToId = $existingMap;
        $inserted = []; // nik => ['line'=>, 'record'=>, 'id'=>]
        $imported = 0;

        foreach ($candidates as $candidate) {
            $record = $candidate['record'];
            $nik = $record['employee_number'];

            try {
                $id = DB::transaction(function () use ($record, $request) {
                    // B1: di luar mass-assignment.
                    $employee = new Employee([
                        'employee_number' => $record['employee_number'],
                        'name' => $record['name'],
                        'department' => $record['department'],
                        'level' => $record['level'],
                        'job_title' => $record['job_title'],
                        'roster' => $record['roster'] ?? null,
                        'employment_status' => $record['employment_status'] ?? 'permanent',
                        'poh_status' => $record['poh_status'] ?? 'non_local',
                        'poh_city' => $record['poh_city'] ?? null,
                        'poh_province' => $record['poh_province'] ?? null,
                        'supervisor_id' => null,
                        'hod_id' => null,
                        'is_project_based' => $this->parseBoolean($record['is_project_based'] ?? null),
                        'active' => $this->parseBoolean($record['active'] ?? '1', true),
                        'joined_at' => $record['joined_at'] ?? null,
                        'ended_at' => $record['ended_at'] ?? null,
                    ]);
                    $employee->created_by = $request->user()->getKey();
                    $employee->updated_by = $request->user()->getKey();
                    $employee->save();

                    return $employee->getKey();
                });

                $nikToId[$nik] = $id;
                $inserted[$nik] = ['line' => $candidate['line'], 'record' => $record, 'id' => $id];
                $imported++;
            } catch (QueryException $e) {
                if ($this->isDuplicateEntry($e)) {
                    $failed[] = [
                        'row' => $candidate['line'],
                        'employee_number' => $nik,
                        'errors' => 'NIK duplikat: sudah terdaftar.',
                    ];

                    continue;
                }
                report($e);
                $failed[] = [
                    'row' => $candidate['line'],
                    'employee_number' => $nik,
                    'errors' => 'Gagal menyimpan baris ini.',
                ];
            } catch (\Throwable $e) {
                report($e);
                $failed[] = [
                    'row' => $candidate['line'],
                    'employee_number' => $nik,
                    'errors' => 'Gagal menyimpan baris ini.',
                ];
            }
        }

        // Pass 2: resolve supervisor/hod dengan cek aktif + cycle DB.
        foreach ($inserted as $nik => $info) {
            $supNik = isset($info['record']['supervisor_number']) && $info['record']['supervisor_number'] !== null
                ? trim((string) $info['record']['supervisor_number']) : null;
            $hodNik = isset($info['record']['hod_number']) && $info['record']['hod_number'] !== null
                ? trim((string) $info['record']['hod_number']) : null;
            if (($supNik === '' || $supNik === null) && ($hodNik === '' || $hodNik === null)) {
                continue;
            }

            $supId = ($supNik !== null && $supNik !== '') ? ($nikToId[$supNik] ?? null) : null;
            $hodId = ($hodNik !== null && $hodNik !== '') ? ($nikToId[$hodNik] ?? null) : null;

            // Ref dalam file yang gagal insert => null-kan dengan laporan.
            $missing = ($supNik !== null && $supNik !== '' && $supId === null)
                || ($hodNik !== null && $hodNik !== '' && $hodId === null);
            if ($missing) {
                $failed[] = [
                    'row' => $info['line'],
                    'employee_number' => $nik,
                    'errors' => 'Referensi atasan tidak dapat di-resolve (baris referensi gagal).',
                ];

                continue;
            }

            $employeeId = (int) $info['id'];
            $cycle = false;
            if ($supId !== null && Employee::wouldCreateCycle($employeeId, (int) $supId, 10)) {
                $cycle = true;
            }
            if ($hodId !== null && Employee::wouldCreateCycle($employeeId, (int) $hodId, 10)) {
                $cycle = true;
            }
            if ($cycle) {
                $failed[] = [
                    'row' => $info['line'],
                    'employee_number' => $nik,
                    'errors' => 'Penugasan atasan membentuk cycle hierarki.',
                ];

                continue;
            }

            // B3: tolak atasan nonaktif (cek ulang DB).
            if ($supId !== null && ! Employee::isActiveLeader((int) $supId)) {
                $failed[] = [
                    'row' => $info['line'],
                    'employee_number' => $nik,
                    'errors' => 'Supervisor harus karyawan aktif.',
                ];
                $supId = null;
            }
            if ($hodId !== null && ! Employee::isActiveLeader((int) $hodId)) {
                $failed[] = [
                    'row' => $info['line'],
                    'employee_number' => $nik,
                    'errors' => 'HOD harus karyawan aktif.',
                ];
                $hodId = null;
            }

            $model = Employee::query()->find($employeeId);
            if ($model !== null) {
                $model->supervisor_id = $supId;
                $model->hod_id = $hodId;
                $model->updated_by = $request->user()->getKey();
                $model->save();
            }
        }

        activity()
            ->causedBy($request->user())
            ->withProperties(['imported' => $imported, 'failed' => count($failed)])
            ->log('employee.imported');

        return redirect()->back()->with('import_result', [
            'imported' => $imported,
            'failed' => $failed,
        ]);
    }

    /**
     * Validasi ketat per baris import (B4): panjang, Rule::in,
     * date_format Y-m-d, ended>=joined, injection, ref forward-resolve.
     *
     * @param  array<string, string|null>  $record
     * @return list<string>
     */
    protected function validateImportRow(
        array $record,
        array $existingMap = [],
        array $fileNikSet = [],
        array $existingActiveMap = [],
        array $fileActiveMap = []
    ): array {
        $errors = [];

        // B4: tolak CSV injection di semua sel.
        foreach ($record as $key => $value) {
            if ($value !== null && $this->isCsvInjection((string) $value)) {
                $errors[] = "Kolom '{$key}' mengandung karakter formula CSV yang dilarang.";

                break;
            }
        }

        $lengths = [
            'employee_number' => 32,
            'name' => 255,
            'department' => 100,
            'level' => 100,
            'job_title' => 150,
            'roster' => 50,
            'poh_city' => 100,
            'poh_province' => 100,
        ];
        foreach ($lengths as $field => $max) {
            if (isset($record[$field]) && $record[$field] !== null && mb_strlen((string) $record[$field]) > $max) {
                $errors[] = "Kolom '{$field}' maksimal {$max} karakter.";
            }
        }

        foreach (['name', 'department', 'level', 'job_title'] as $field) {
            if (($record[$field] ?? null) === null || trim((string) $record[$field]) === '') {
                $errors[] = "Kolom '{$field}' wajib diisi.";
            }
        }

        $employment = $record['employment_status'] ?? 'permanent';
        if ($employment === null || $employment === '') {
            $employment = 'permanent';
        }
        if (! in_array($employment, ['permanent', 'contract', 'probation', 'resigned', 'terminated'], true)) {
            $errors[] = 'employment_status harus salah satu: permanent, contract, probation, resigned, terminated.';
        }

        $poh = $record['poh_status'] ?? 'non_local';
        if ($poh === null || $poh === '') {
            $poh = 'non_local';
        }
        if (! in_array($poh, ['local', 'non_local'], true)) {
            $errors[] = "poh_status harus 'local' atau 'non_local'.";
        }

        foreach (['joined_at', 'ended_at'] as $dateField) {
            $value = $record[$dateField] ?? null;
            if ($value !== null && $value !== '' && ! $this->isStrictDate((string) $value)) {
                $errors[] = "Kolom '{$dateField}' harus format Y-m-d.";
            }
        }

        $joined = $record['joined_at'] ?? null;
        $ended = $record['ended_at'] ?? null;
        if (
            $joined !== null && $joined !== ''
            && $ended !== null && $ended !== ''
            && $this->isStrictDate((string) $joined)
            && $this->isStrictDate((string) $ended)
            && $ended < $joined
        ) {
            $errors[] = "Kolom 'ended_at' harus >= joined_at.";
        }

        $ownNik = trim((string) ($record['employee_number'] ?? ''));
        foreach (['supervisor_number', 'hod_number'] as $refField) {
            $value = isset($record[$refField]) && $record[$refField] !== null
                ? trim((string) $record[$refField]) : null;
            if ($value === null || $value === '') {
                continue;
            }

            // B3: tolak self-ref.
            if ($value === $ownNik) {
                $errors[] = "Kolom '{$refField}' tidak boleh diri sendiri.";

                continue;
            }

            // Forward-ref: boleh ada di DB maupun di file yang sama.
            $inDb = isset($existingMap[$value]);
            $inFile = isset($fileNikSet[$value]);
            if (! $inDb && ! $inFile) {
                $errors[] = "Kolom '{$refField}' ({$value}) tidak ditemukan di master.";

                continue;
            }

            // B3: atasan harus aktif.
            if ($inDb && ! isset($existingActiveMap[$value])) {
                $errors[] = "Kolom '{$refField}' ({$value}) harus karyawan aktif.";

                continue;
            }
            if (! $inDb && $inFile && ($fileActiveMap[$value] ?? true) === false) {
                $errors[] = "Kolom '{$refField}' ({$value}) harus karyawan aktif.";

                continue;
            }
        }

        return $errors;
    }

    /**
     * B4: deteksi formula CSV (=,+,-,@ di awal + TAB/CR).
     */
    protected function isCsvInjection(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $trimmed = ltrim($value, " \t\n\r\0\x0B　");
        if ($trimmed === '') {
            return false;
        }

        $first = $trimmed[0];

        return $first === '='
            || $first === '+'
            || $first === '-'
            || $first === '@'
            || $first === "\t"
            || $first === "\r"
            || $first === "\n";
    }

    protected function isStrictDate(string $value): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }

    /**
     * Cycle check gabungan file-graph + DB (B3, max depth 10).
     *
     * @param  array<string, array{sup: string|null, hod: string|null}>  $parentMap
     */
    protected function fileWouldCreateCycle(string $nik, array $parentMap, int $maxDepth = 10): bool
    {
        $start = $parentMap[$nik] ?? null;
        if ($start === null) {
            return false;
        }

        $queue = [];
        if ($start['sup'] !== null && $start['sup'] !== '') {
            $queue[] = $start['sup'];
        }
        if ($start['hod'] !== null && $start['hod'] !== '') {
            $queue[] = $start['hod'];
        }

        $visited = [];

        for ($depth = 0; $depth < $maxDepth && $queue !== []; $depth++) {
            $next = [];

            foreach ($queue as $current) {
                if ($current === $nik) {
                    return true;
                }

                if (isset($visited[$current])) {
                    continue;
                }
                $visited[$current] = true;

                if (isset($parentMap[$current])) {
                    $parents = $parentMap[$current];
                    if ($parents['sup'] !== null && $parents['sup'] !== '') {
                        $next[] = $parents['sup'];
                    }
                    if ($parents['hod'] !== null && $parents['hod'] !== '') {
                        $next[] = $parents['hod'];
                    }
                } else {
                    // NIK dari DB: walk ancestor DB.
                    $db = Employee::query()->select(['supervisor_id', 'hod_id'])
                        ->where('employee_number', $current)->first();
                    if ($db === null) {
                        continue;
                    }
                    if ($db->supervisor_id !== null) {
                        $supNik = Employee::query()->whereKey($db->supervisor_id)->value('employee_number');
                        if (is_string($supNik) && $supNik !== '') {
                            $next[] = $supNik;
                        }
                    }
                    if ($db->hod_id !== null) {
                        $hodNik = Employee::query()->whereKey($db->hod_id)->value('employee_number');
                        if (is_string($hodNik) && $hodNik !== '') {
                            $next[] = $hodNik;
                        }
                    }
                }
            }

            $queue = $next;
        }

        return false;
    }

    protected function parseBoolean(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'ya', 'y', 'aktif', 'active'], true);
    }

    /**
     * User yang bisa di-link (belum terpakai karyawan lain).
     */
    protected function linkableUsers(?Employee $except = null): mixed
    {
        $usedUserIds = Employee::whereNotNull('user_id')
            ->when($except, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->pluck('user_id')
            ->all();

        return User::query()
            ->select(['id', 'name', 'email'])
            ->whereNotIn('id', $usedUserIds)
            ->orderBy('name')
            ->limit(200)
            ->get();
    }

    /**
     * Opsi supervisor/HOD (karyawan aktif).
     */
    protected function employeeOptions(?Employee $except = null): mixed
    {
        return Employee::query()
            ->select(['id', 'employee_number', 'name', 'department'])
            ->where('active', true)
            ->when($except, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->orderBy('name')
            ->limit(500)
            ->get();
    }
}
