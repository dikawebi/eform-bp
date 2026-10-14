<?php

namespace App\Services\Approval;

use App\Enums\ApprovalStepStatus;
use App\Enums\RequestStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\InAppNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Penarikan kembali transaksi oleh pemilik (withdraw).
 *
 * Dipakai oleh cancel semua modul saat pengajuan sudah masuk proses
 * (Submitted/InReview) bahkan sudah Approved, selama belum ada uang
 * yang bergerak (AdvancePaid/SettlementRequired/PaymentProcessing/
 * Completed) dan belum terminal. Harus dipanggil di dalam DB transaction
 * setelah parent di-lock; policy cancel masing-masing modul tetap
 * menjadi gerbang utama dan boleh lebih ketat dari penjagaan di sini.
 *
 * Tahap yang sudah Approved/Rejected/Returned/Skipped dipertahankan
 * sebagai riwayat; hanya tahap Pending yang dibatalkan. Approver yang
 * sedang memegang tahap diberi tahu agar inbox-nya tidak menggantung.
 */
final class WithdrawRequest
{
    /**
     * @return array{approved_count: int, notified: list<int>}
     *
     * @throws ValidationException
     */
    public static function run(Model $parent, User $actor, ?string $reason = null): array
    {
        if (! in_array($parent->status, [RequestStatus::Draft, RequestStatus::Submitted, RequestStatus::Returned, RequestStatus::InReview, RequestStatus::Approved, RequestStatus::Processing], true)) {
            throw ValidationException::withMessages(['status' => 'Pengajuan pada status ini tidak dapat ditarik kembali.']);
        }

        $reason = trim((string) ($reason ?? ''));
        $morph = $parent->getMorphClass();
        $generation = ApprovalRequest::query()->where('approvable_type', $morph)->where('approvable_id', $parent->getKey())->orderByDesc('id')->value('chain_generation');

        $approvedCount = 0;
        $notified = [];

        if ($generation !== null) {
            $approvedCount = ApprovalRequest::query()->where('approvable_type', $morph)->where('approvable_id', $parent->getKey())->where('chain_generation', $generation)->where('status', ApprovalStepStatus::Approved)->count();

            $pending = ApprovalRequest::query()->where('approvable_type', $morph)->where('approvable_id', $parent->getKey())->where('chain_generation', $generation)->where('status', ApprovalStepStatus::Pending)->lockForUpdate()->get();

            $context = InAppNotifier::context($parent, $actor);
            $document = trim(($context['document_number'] ?? '').' '.$context['module']);

            foreach ($pending as $row) {
                $row->forceFill([
                    'status' => ApprovalStepStatus::Cancelled,
                    'acted_at' => now(),
                    'comments' => $reason !== '' ? "Ditarik kembali: {$reason}" : 'Ditarik kembali oleh pemohon',
                ])->save();

                activity()->performedOn($parent)->causedBy($actor)->withProperties([
                    'approval_id' => $row->id, 'generation' => $generation, 'reason' => 'withdrawn',
                ])->log('approval.chain_step_cancelled');

                if ($row->approver_user_id !== null) {
                    app(InAppNotifier::class)->notifyUser(
                        (int) $row->approver_user_id,
                        'approval.withdrawn',
                        'Pengajuan ditarik kembali',
                        trim("{$document} ditarik kembali oleh {$actor->name}."),
                        app(InAppNotifier::class)->url($parent),
                        array_merge($context, ['step' => $row->step_code]),
                    );
                    $notified[] = (int) $row->approver_user_id;
                }
            }
        }

        activity()->performedOn($parent)->causedBy($actor)->withProperties([
            'approved_count' => $approvedCount,
            'notified_approver_ids' => array_values(array_unique($notified)),
            'reason' => $reason !== '' ? $reason : null,
        ])->log('approval.chain_withdrawn');

        return ['approved_count' => $approvedCount, 'notified' => array_values(array_unique($notified))];
    }
}
