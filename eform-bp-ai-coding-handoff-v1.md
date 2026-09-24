# eForm BP — AI Coding Agent Handoff v1.1

## Tujuan

Bangun aplikasi internal eForm BP untuk Cuti/Izin, Perjalanan Dinas, Settlement, dan Medical Claim.

## Stack wajib

- Laravel 12.x
- PHP 8.3
- MySQL 8 atau MariaDB 10.6+
- React
- Inertia.js
- Vite
- Tailwind CSS
- shadcn/ui
- Spatie Laravel Permission
- Spatie Activitylog
- Database queue, bukan Redis wajib
- DomPDF untuk PDF MVP

## Prinsip arsitektur

1. Gunakan Laravel sebagai backend, routing, authorization, validation, database access, notification, dan workflow engine.
2. Gunakan Inertia untuk mengirim props dari Laravel ke halaman React.
3. Gunakan shadcn/ui sebagai komponen dasar, tetapi buat halaman dengan visual modern dan tidak menyerupai Filament.
4. Gunakan Form Request untuk validasi.
5. Gunakan Policies dan Gates untuk authorization.
6. Gunakan service/action class untuk kalkulasi total dan transisi status.
7. Jangan meletakkan aturan bisnis penting hanya di React.
8. Jangan menggunakan Excel sebagai database runtime.
9. Sertakan modul Cuti/Izin dan hubungkan sumber advance-nya ke Settlement.
10. Semua status transition harus dicatat di activity log.

## Urutan implementasi

### Phase 0 — Foundation

- Setup Laravel, Inertia React, Vite, Tailwind, shadcn/ui.
- Setup authentication.
- Setup MySQL.
- Setup roles and permissions.
- Setup private file storage.
- Setup database queue.
- Buat layout aplikasi: sidebar, topbar, breadcrumbs, toast, modal, empty state.

### Phase 1 — Master Karyawan

- Migration employees.
- Import employee CSV.
- CRUD karyawan untuk admin.
- Employee selector/search.
- Mapping supervisor dan HOD.

### Phase 2 — Cuti / Izin

- Migration leave_requests, leave_periods, dan leave_cost_items.
- Form periode cuti/izin dinamis.
- Kalkulasi jumlah hari per periode dan total hari.
- Form alasan/catatan.
- Aturan biaya lokal/non-lokal yang configurable.
- Draft/save/submit.
- Attachment upload.
- Detail read-only untuk status non-editable.

### Phase 3 — Perjalanan Dinas

- Migration travel_requests dan travel_request_items.
- Form header perjalanan.
- Repeater item biaya.
- Kalkulasi otomatis di server.
- Draft/save/submit.
- Attachment upload.
- Detail read-only untuk status non-editable.

### Phase 4 — Approval Engine

- Migration approval_workflows, approval_workflow_steps, approval_requests, approval_actions.
- Configurable approval matrix.
- Approval inbox.
- Approve/reject/return/delegate.
- Comments wajib untuk reject dan return.
- Notification database dan email.

### Phase 5 — Advance dan Settlement

- Advance processing status.
- Settlement creation from eligible leave request or travel request.
- Settlement item repeater.
- Reconciliation service.
- Overpayment/underpayment/balanced result.
- Finance review.

### Phase 6 — Medical Claim

- Medical claim form.
- Patient/relationship rows.
- Benefit type configuration.
- Attachments.
- HRGA review.

### Phase 7 — Reports and hardening

- Dashboard summary.
- Report filters/export.
- Audit timeline.
- Policy tests.
- File access tests.
- Shared-hosting deployment guide.

## Model dan service yang diharapkan

- Employee
- LeaveRequest
- LeavePeriod
- LeaveCostItem
- TravelRequest
- TravelRequestItem
- Settlement
- SettlementItem
- MedicalClaim
- MedicalClaimItem
- Attachment
- ApprovalWorkflow
- ApprovalWorkflowStep
- ApprovalRequest
- ApprovalAction

Service/action:

- CalculateTravelAdvance
- CalculateSettlement
- SubmitTravelRequest
- SubmitLeaveRequest
- SubmitSettlement
- SubmitMedicalClaim
- ApproveApprovalRequest
- ReturnApprovalRequest
- RejectApprovalRequest
- BuildApprovalChain

## Status constants

Gunakan PHP Enum jika memungkinkan:

- Draft
- Submitted
- InReview
- Returned
- Rejected
- Approved
- Processing
- AdvancePaid
- SettlementRequired
- PaymentProcessing
- Completed
- Cancelled

## Approval rules default

### Leave request

1. Employee submit.
2. Supervisor/SPT review jika workflow matrix mengharuskan.
3. HOD approval.
4. Project Manager approval jika diperlukan.
5. HRGA processing.
6. Advance paid atau approved tanpa biaya.

### Travel request

1. Employee submit.
2. Supervisor/SPT review jika workflow matrix mengharuskan.
3. HOD approval.
4. Project Manager approval jika project approval aktif.
5. HRGA processing.
6. Advance paid.

### Settlement

1. Employee submit settlement.
2. HRGA document review.
3. Finance review.
4. Payment or adjustment processing.
5. Completed.

### Medical claim

1. Employee submit.
2. HRGA review.
3. Document validation.
4. Approval.
5. Payment processing.
6. Completed.

## UI requirements

- Bahasa Indonesia.
- Desktop-first, responsive untuk tablet dan mobile.
- Sidebar dengan menu berdasarkan permission.
- Status badge konsisten.
- Gunakan stepper pada halaman detail.
- Gunakan summary card untuk total advance, settlement, dan selisih.
- Gunakan table untuk daftar transaksi.
- Gunakan drawer/modal untuk approval action.
- Tampilkan warning sebelum submit.
- Tampilkan alasan return/reject pada timeline.
- Hindari form Excel yang terlalu panjang dalam satu halaman.
- Menu utama harus mencakup Cuti/Izin, Perjalanan Dinas, Settlement, Medical Claim, Approval, Laporan, Master Data, dan Pengaturan.

## Testing minimum

- Employee hanya melihat transaksi miliknya.
- Approver hanya melihat approval yang ditugaskan.
- User tidak dapat approve transaksi sendiri.
- Returned transaction dapat diedit kembali.
- Approved transaction tidak dapat diedit langsung.
- Leave request menghitung jumlah hari setiap periode dengan benar.
- Leave request dapat memiliki beberapa periode tanggal dan kategori biaya.
- Leave request lokal/non-lokal menerapkan aturan biaya yang benar.
- Perhitungan item biaya benar.
- Settlement tidak dapat dibuat sebelum sumber advance cuti atau dinas eligible.
- Settlement dapat mengambil sumber dari leave request atau travel request.
- Cuti dengan biaya wajib memiliki kategori biaya dan perhitungan total yang dapat ditelusuri.
- Selisih settlement benar untuk tiga kondisi.
- Medical attachment tidak dapat diakses tanpa permission.
- Semua approval action masuk audit log.

## Deployment shared hosting

- Build frontend sebelum upload.
- Set document root ke `/public` jika provider mendukung.
- Jika tidak, gunakan struktur public_html yang aman dan arahkan index.php dengan benar.
- Jalankan migration melalui SSH atau proses deployment.
- Gunakan database queue.
- Tambahkan cron untuk `php artisan schedule:run`.
- Gunakan SMTP.
- Jangan mengandalkan Reverb/WebSocket untuk MVP.
- Jangan membutuhkan proses daemon permanen.

## Larangan untuk agent

- Jangan menghilangkan modul cuti/izin.
- Jangan menggunakan Filament sebagai UI utama.
- Jangan menggunakan Docker kecuali diminta.
- Jangan mengganti MySQL kembali ke PostgreSQL.
- Jangan membuat approval hard-coded di controller.
- Jangan menyimpan file medical claim sebagai public URL.
- Jangan menghapus data transaksi secara fisik dari UI biasa.
- Jangan menandai status approved hanya berdasarkan frontend.
