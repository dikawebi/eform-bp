<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\MedicalClaim;
use App\Models\Settlement;
use App\Models\TravelRequest;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Database\Eloquent\Model;

final class InAppNotifier
{
    public function notifyUser(?int $userId, string $event, string $title, string $message, string $url): void
    {
        if ($userId === null) {
            return;
        }

        User::query()->whereKey($userId)->first()?->notify(new WorkflowNotification($event, $title, $message, $url));
    }

    public function notifyOwner(Model $subject, string $event, string $title, string $message): void
    {
        $subject->loadMissing('employee:id,user_id');
        $employee = $subject->employee;

        if (! $employee?->user_id) {
            return;
        }

        $this->notifyUser($employee->user_id, $event, $title, $message, $this->url($subject));
    }

    public function url(Model $subject): string
    {
        return match (true) {
            $subject instanceof LeaveRequest => route('leaves.show', $subject),
            $subject instanceof TravelRequest => route('travels.show', $subject),
            $subject instanceof Settlement => route('settlements.show', $subject),
            $subject instanceof MedicalClaim => route('medical-claims.show', $subject),
            default => route('dashboard'),
        };
    }

    public function label(Model $subject): string
    {
        return match (true) {
            $subject instanceof LeaveRequest => 'Pengajuan cuti/izin',
            $subject instanceof TravelRequest => 'Pengajuan perjalanan dinas',
            $subject instanceof Settlement => 'Settlement',
            $subject instanceof MedicalClaim => 'Medical Claim',
            default => 'Transaksi eForm BP',
        };
    }
}
