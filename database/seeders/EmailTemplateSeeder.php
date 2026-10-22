<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Mengisi template email bawaan (default untuk semua modul) agar halaman
 * Pengaturan > Email & Notifikasi > Template tidak kosong. Memakai
 * firstOrCreate supaya template yang sudah diedit admin tidak tertimpa
 * saat seeder dijalankan ulang.
 *
 * Placeholder yang didukung diisi otomatis oleh SendWorkflowEmail:
 * {nomor_dokumen}, {modul}, {pelaku}, {tahap}, {judul}, {pesan}, {tautan}.
 */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $event => $content) {
            EmailTemplate::query()->firstOrCreate(
                ['module' => null, 'event' => $event],
                $content,
            );
        }
    }

    /**
     * @return array<string, array{subject: string, message: string, subject_en: string, message_en: string}>
     */
    private function templates(): array
    {
        return [
            'approval.assigned' => [
                'subject' => 'Approval baru menunggu Anda — {nomor_dokumen}',
                'message' => 'Pengajuan {modul} nomor {nomor_dokumen} dari {pelaku} menunggu pemeriksaan Anda pada tahap {tahap}. Silakan buka eForm BP untuk meninjau detail dan memberikan keputusan.',
                'subject_en' => 'New approval awaiting you — {document_number}',
                'message_en' => 'The {module} request number {document_number} from {actor} is awaiting your review at the {step} stage. Please open eForm BP to review the details and provide a decision.',
            ],
            'approval.returned' => [
                'subject' => 'Pengajuan {nomor_dokumen} perlu diperbaiki',
                'message' => 'Pengajuan {modul} nomor {nomor_dokumen} dikembalikan oleh {pelaku} pada tahap {tahap}. Silakan buka eForm BP dan periksa catatan pada transaksi.',
                'subject_en' => 'Request {document_number} needs revision',
                'message_en' => 'The {module} request number {document_number} was returned by {actor} at the {step} stage. Please open eForm BP and review the notes on the transaction.',
            ],
            'approval.rejected' => [
                'subject' => 'Pengajuan {nomor_dokumen} ditolak',
                'message' => 'Pengajuan {modul} nomor {nomor_dokumen} ditolak oleh {pelaku} pada tahap {tahap}. Silakan buka eForm BP dan periksa catatan pada transaksi.',
                'subject_en' => 'Request {document_number} rejected',
                'message_en' => 'The {module} request number {document_number} was rejected by {actor} at the {step} stage. Please open eForm BP and review the notes on the transaction.',
            ],
            'approval.approved' => [
                'subject' => 'Tahap {tahap} pengajuan {nomor_dokumen} disetujui',
                'message' => 'Tahap {tahap} pada pengajuan {modul} nomor {nomor_dokumen} telah disetujui oleh {pelaku}. Silakan buka eForm BP untuk melihat status terbaru.',
                'subject_en' => 'Stage {step} of request {document_number} approved',
                'message_en' => 'The {step} stage of the {module} request number {document_number} has been approved by {actor}. Please open eForm BP to see the latest status.',
            ],
            'approval.delegated' => [
                'subject' => 'Approval {nomor_dokumen} didelegasikan kepada Anda',
                'message' => 'Pengajuan {modul} nomor {nomor_dokumen} didelegasikan oleh {pelaku} kepada Anda. Silakan buka eForm BP untuk meninjau detail pada tahap {tahap}.',
                'subject_en' => 'Approval {document_number} delegated to you',
                'message_en' => 'The {module} request number {document_number} was delegated to you by {actor}. Please open eForm BP to review the details at the {step} stage.',
            ],
            'approval.cancelled' => [
                'subject' => 'Approval {nomor_dokumen} dibatalkan',
                'message' => 'Tahap {tahap} pada pengajuan {modul} nomor {nomor_dokumen} telah dibatalkan sehingga tidak lagi menunggu keputusan Anda.',
                'subject_en' => 'Approval {document_number} cancelled',
                'message_en' => 'The {step} stage of the {module} request number {document_number} has been cancelled and no longer awaits your decision.',
            ],
            'approval.withdrawn' => [
                'subject' => 'Pengajuan {nomor_dokumen} ditarik kembali',
                'message' => 'Pengajuan {modul} nomor {nomor_dokumen} telah ditarik oleh {pelaku} sehingga tidak lagi menunggu keputusan Anda.',
                'subject_en' => 'Request {document_number} withdrawn',
                'message_en' => 'The {module} request number {document_number} has been withdrawn by {actor} and no longer awaits your decision.',
            ],
            'user.employee_linked' => [
                'subject' => 'Akun Anda berhasil terhubung ke data karyawan',
                'message' => 'Akun eForm BP Anda telah dihubungkan ke data karyawan NIK {nomor_dokumen} oleh {pelaku}. Anda sekarang dapat membuat pengajuan untuk data karyawan tersebut. Buka eForm BP melalui tautan berikut: {tautan}',
                'subject_en' => 'Your account has been linked to employee data',
                'message_en' => 'Your eForm BP account has been linked to employee NIK {document_number} by {actor}. You can now create requests for this employee. Open eForm BP using the following link: {tautan}',
            ],
            'leave.submitted' => [
                'subject' => 'Pengajuan cuti/izin {nomor_dokumen} diserahkan',
                'message' => 'Pengajuan cuti/izin nomor {nomor_dokumen} telah diserahkan oleh {pelaku} dan sedang menunggu proses persetujuan.',
                'subject_en' => 'Leave/permission request {document_number} submitted',
                'message_en' => 'The leave/permission request number {document_number} has been submitted by {actor} and is awaiting approval.',
            ],
            'travel.submitted' => [
                'subject' => 'Pengajuan perjalanan dinas {nomor_dokumen} diserahkan',
                'message' => 'Pengajuan perjalanan dinas nomor {nomor_dokumen} telah diserahkan oleh {pelaku} dan sedang menunggu proses persetujuan.',
                'subject_en' => 'Business travel request {document_number} submitted',
                'message_en' => 'The business travel request number {document_number} has been submitted by {actor} and is awaiting approval.',
            ],
            'settlement.submitted' => [
                'subject' => 'Settlement {nomor_dokumen} diserahkan',
                'message' => 'Settlement nomor {nomor_dokumen} telah diserahkan oleh {pelaku} dan sedang menunggu proses review.',
                'subject_en' => 'Settlement {document_number} submitted',
                'message_en' => 'The settlement number {document_number} has been submitted by {actor} and is awaiting review.',
            ],
            'settlement.completed' => [
                'subject' => 'Settlement {nomor_dokumen} selesai',
                'message' => 'Proses settlement nomor {nomor_dokumen} telah selesai. Silakan buka eForm BP untuk melihat rincian akhir.',
                'subject_en' => 'Settlement {document_number} completed',
                'message_en' => 'The settlement number {document_number} has been completed. Please open eForm BP to view the final details.',
            ],
            'medical.submitted' => [
                'subject' => 'Medical claim {nomor_dokumen} diserahkan',
                'message' => 'Klaim kesehatan nomor {nomor_dokumen} telah diserahkan oleh {pelaku} dan sedang menunggu proses review.',
                'subject_en' => 'Medical claim {document_number} submitted',
                'message_en' => 'The medical claim number {document_number} has been submitted by {actor} and is awaiting review.',
            ],
            'it.submitted' => [
                'subject' => 'Permintaan IT {nomor_dokumen} diserahkan',
                'message' => 'Permintaan Hardware & Software (Non-ERP) nomor {nomor_dokumen} telah diserahkan oleh {pelaku} dan sedang menunggu proses persetujuan.',
                'subject_en' => 'IT request {document_number} submitted',
                'message_en' => 'The Hardware & Software (Non-ERP) request number {document_number} has been submitted by {actor} and is awaiting approval.',
            ],
            'erp.submitted' => [
                'subject' => 'Permintaan ERP {nomor_dokumen} diserahkan',
                'message' => 'Permintaan ERP nomor {nomor_dokumen} telah diserahkan oleh {pelaku} dan sedang menunggu proses persetujuan.',
                'subject_en' => 'ERP request {document_number} submitted',
                'message_en' => 'The ERP request number {document_number} has been submitted by {actor} and is awaiting approval.',
            ],
            'it.completed' => [
                'subject' => 'Permintaan IT {nomor_dokumen} selesai',
                'message' => 'Permintaan Hardware & Software (Non-ERP) nomor {nomor_dokumen} telah selesai diproses.',
                'subject_en' => 'IT request {document_number} completed',
                'message_en' => 'The Hardware & Software (Non-ERP) request number {document_number} has been completed.',
            ],
            'it.pr_ready' => [
                'subject' => 'Approval IT {nomor_dokumen} selesai — lanjutkan pembuatan PR',
                'message' => 'Permintaan Hardware & Software (Non-ERP) nomor {nomor_dokumen} telah disetujui seluruh tahap. Silakan lanjutkan proses pembuatan PR/MSD. Dokumen PDF terlampir.',
                'subject_en' => 'IT approval {document_number} completed — proceed with PR',
                'message_en' => 'The Hardware & Software (Non-ERP) request number {document_number} has completed all approval stages. Please proceed with the PR/MSD process. The PDF document is attached.',
            ],
            'erp.completed' => [
                'subject' => 'Permintaan ERP {nomor_dokumen} selesai',
                'message' => 'Permintaan ERP nomor {nomor_dokumen} telah selesai diproses.',
                'subject_en' => 'ERP request {document_number} completed',
                'message_en' => 'The ERP request number {document_number} has been completed.',
            ],
        ];
    }
}
