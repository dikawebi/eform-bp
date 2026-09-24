<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const MODULES = ['leave', 'travel', 'settlement', 'medical'];

    public function dashboard(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('dashboard.view'), 403);
        $filters = [];
        $summary = [];
        foreach (self::MODULES as $module) {
            $query = $this->query($module, $request->user(), $filters);
            $summary[$module] = $query->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        }

        return response()->json(['summary' => $summary]);
    }

    public function index(ReportFilterRequest $request): Response|StreamedResponse
    {
        abort_unless($request->user()->can('report.view'), 403);
        $data = $request->validated();
        $modules = isset($data['module']) ? [$data['module']] : self::MODULES;
        if ($request->boolean('export')) {
            abort_unless($request->user()->can('report.export'), 403);

            return response()->streamDownload(function () use ($modules, $data, $request): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['module', 'document_number', 'status', 'date', 'employee_id', 'amount']);
                foreach ($modules as $module) {
                    $this->query($module, $request->user(), $data)->chunkById(500, function ($models) use ($out, $module, $request): void {
                        foreach ($models as $model) {
                            fputcsv($out, $this->row($module, $model, $request->user()));
                        }
                    });
                }
                fclose($out);
            }, 'eform-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
        }

        $reportQuery = $this->reportQuery($modules, $request->user(), $data);
        $perPage = (int) ($data['per_page'] ?? 25);
        $page = max(1, (int) $request->input('page', 1));
        $paginator = $reportQuery->paginate($perPage, ['*'], 'page', $page);
        $paginator->setCollection($paginator->getCollection()
            ->map(fn (object $row) => $this->row((string) $row->module, $row, $request->user()))
            ->values());
        $canSeeAggregate = $request->user()->can('report.amount.view') || ($request->user()->can('medical.view.aggregate') && ! $request->user()->hasRole('auditor'));
        $completed = 0;
        $totalAmount = $canSeeAggregate ? 0 : null;
        foreach ($modules as $module) {
            $aggregateQuery = $this->query($module, $request->user(), $data);
            $completed += (clone $aggregateQuery)->where('status', RequestStatus::Completed->value)->count();
            if ($canSeeAggregate) {
                $totalAmount += (float) $aggregateQuery->sum($module === 'medical' ? 'total_amount' : ($module === 'travel' || $module === 'leave' ? 'total_advance' : 'actual_amount'));
            }
        }

        return Inertia::render('Reports/Index', ['report' => ['data' => $paginator->items(), 'total' => $paginator->total(), 'completed' => $completed, 'total_amount' => $totalAmount, 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'links' => $paginator->linkCollection()->toArray()], 'filters' => $data, 'statuses' => RequestStatus::values(), 'canExport' => $request->user()->can('report.export')]);
    }

    private function query(string $module, $user, array $filters)
    {
        $map = ['leave' => [LeaveRequest::class, 'submitted_at'], 'travel' => [TravelRequest::class, 'submitted_at'], 'settlement' => [Settlement::class, 'submitted_at'], 'medical' => [MedicalClaim::class, 'submitted_at']];
        [$class, $date] = $map[$module];
        $query = $class::query()->whereNotNull($date);
        if (! $this->canViewAll($module, $user)) {
            $query->whereIn('employee_id', Employee::query()->select('id')->where('user_id', $user->id));
        }
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['date_from'])) {
            $query->whereDate($date, '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate($date, '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * Normalize report rows in SQL before pagination so the database bounds
     * both the result set and the aggregate work for large installations.
     */
    private function reportQuery(array $modules, $user, array $filters): Builder
    {
        $columns = [
            'leave' => ['leave_requests', 'request_number'],
            'travel' => ['travel_requests', 'request_number'],
            'settlement' => ['settlements', 'settlement_number'],
            'medical' => ['medical_claims', 'claim_number'],
        ];
        $dateColumns = ['leave' => 'submitted_at', 'travel' => 'submitted_at', 'settlement' => 'submitted_at', 'medical' => 'submitted_at'];
        $amountColumns = ['leave' => 'total_advance', 'travel' => 'total_advance', 'settlement' => 'actual_amount', 'medical' => 'total_amount'];
        $queries = [];

        foreach ($modules as $module) {
            [$table, $numberColumn] = $columns[$module];
            $dateColumn = $dateColumns[$module];
            $query = DB::table($table)
                ->selectRaw('? as module, id, '.$numberColumn.' as document_number, status, '.$dateColumn.' as report_date, employee_id, '.$amountColumns[$module].' as amount', [$module])
                ->whereNotNull($dateColumn);

            if (! $this->canViewAll($module, $user)) {
                $query->whereIn('employee_id', Employee::query()->select('id')->where('user_id', $user->id));
            }
            if (isset($filters['employee_id'])) {
                $query->where('employee_id', $filters['employee_id']);
            }
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            if (isset($filters['date_from'])) {
                $query->whereDate($dateColumn, '>=', $filters['date_from']);
            }
            if (isset($filters['date_to'])) {
                $query->whereDate($dateColumn, '<=', $filters['date_to']);
            }
            $queries[] = $query;
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'report_rows')
            ->orderByDesc('report_date')
            ->orderByDesc('id');
    }

    private function canViewAll(string $module, $user): bool
    {
        return ($module === 'medical' && ($user->can('medical.view.all') || $user->can('medical.view.aggregate')))
            || ($module === 'leave' && $user->can('leave.view.all'))
            || ($module === 'travel' && $user->can('travel.view.all'))
            || ($module === 'settlement' && $user->can('settlement.view.all'));
    }

    private function row(string $module, $model, $user): array
    {
        $medical = $module === 'medical';
        $sensitive = $medical && ($user->can('medical.view.sensitive') || $user->can('medical.view.all'));

        $status = $model->status instanceof RequestStatus ? $model->status->value : (string) $model->status;
        $date = $model->report_date ?? $model->submitted_at;
        $date = $date instanceof CarbonInterface ? $date->toDateString() : (is_string($date) ? substr($date, 0, 10) : null);

        return ['module' => $module, 'document_number' => $model->document_number ?? ($medical ? $model->claim_number : ($model->request_number ?? $model->settlement_number)), 'status' => $status, 'date' => $date, 'employee_id' => $sensitive || ! $medical ? $model->employee_id : null, 'amount' => $medical && ! $sensitive ? null : ($model->amount ?? $model->total_amount ?? $model->total_advance ?? $model->actual_amount ?? null)];
    }
}
