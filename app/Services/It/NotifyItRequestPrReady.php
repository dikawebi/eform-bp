<?php

namespace App\Services\It;

use App\Models\ItRequest;
use App\Models\User;
use App\Services\InAppNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Memberi tahu tim IT bahwa seluruh approval IT Request selesai sehingga
 * proses pembuatan PR/MSD dapat dilanjutkan. Dokumen PDF disertakan pada
 * email; bila PDF gagal dibuat, notifikasi tetap dikirim tanpa lampiran.
 */
final class NotifyItRequestPrReady
{
    public static function run(ItRequest $request, User $actor): void
    {
        $recipients = User::query()
            ->where('active', true)
            ->role('it')
            ->permission('it_request.review')
            ->whereHas('employee', fn ($employee) => $employee->where('active', true))
            ->orderBy('name')
            ->get();

        if ($recipients->isEmpty()) {
            Log::warning('IT request selesai tetapi tidak ada user IT aktif untuk diberi tahu', [
                'it_request_id' => $request->id,
                'request_number' => $request->request_number,
            ]);

            return;
        }

        $attachments = [];
        try {
            $attachments = [ItRequestPdf::store($request)];
        } catch (Throwable $exception) {
            Log::error('PDF IT request gagal dibuat untuk lampiran email', [
                'it_request_id' => $request->id,
                'request_number' => $request->request_number,
                'error' => $exception->getMessage(),
            ]);
        }

        $message = "Permintaan {$request->request_number} telah disetujui seluruh tahap. "
            .'Silakan lanjutkan proses pembuatan PR/MSD.'
            .($attachments !== [] ? ' Dokumen PDF terlampir.' : '');

        foreach ($recipients as $recipient) {
            app(InAppNotifier::class)->notifyUser(
                $recipient->id,
                'it.pr_ready',
                'Approval selesai — lanjutkan pembuatan PR',
                $message,
                route('it-requests.show', $request),
                InAppNotifier::context($request, $actor),
                attachments: $attachments,
            );
        }
    }
}
