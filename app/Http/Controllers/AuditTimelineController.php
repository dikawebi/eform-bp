<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class AuditTimelineController extends Controller
{
    public function __invoke(Request $request, string $module, int $id): JsonResponse
    {
        $map = ['leave' => LeaveRequest::class, 'travel' => TravelRequest::class, 'settlement' => Settlement::class, 'medical' => MedicalClaim::class];
        abort_unless(isset($map[$module]), 404);
        $model = $map[$module]::findOrFail($id);
        $user = $request->user();
        Gate::forUser($user)->authorize('view', $model);
        $owner = (int) $model->created_by === (int) $user->id
            || $model->employee?->user_id === $user->id;
        $medicalSensitive = $module !== 'medical' || $user->can('medical.view.sensitive') || $owner;
        $items = Activity::query()->where('subject_type', $model->getMorphClass())->where('subject_id', $model->id)->latest()->limit(100)->get()->map(fn (Activity $activity) => ['event' => $activity->description, 'actor' => $medicalSensitive ? $activity->causer?->name : null, 'at' => $activity->created_at?->toISOString(), 'properties' => $medicalSensitive && $module !== 'medical' ? $activity->properties->only(['from', 'to', 'action']) : []])->values();

        return response()->json(['data' => $items]);
    }
}
