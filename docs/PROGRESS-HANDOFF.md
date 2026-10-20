# eForm BP Development Handoff

Tanggal catatan: 28 September 2026

Dokumen ini adalah snapshot progress untuk melanjutkan development dari device lain.

## Status Utama

Implementasi sudah mencakup:

- Modul Cuti/Izin, Perjalanan Dinas, Settlement, dan Medical Claim.
- Workflow stepper reusable dengan status dan PIC approval/proses.
- Form transaksi dan detail read-only.
- Role/permission management untuk admin.
- Pembatasan visibilitas transaksi berdasarkan ownership dan struktur bawahan.
- Seeder akun demo Andika dan Heru.
- Validasi server-side, kalkulasi advance, settlement, attachment private, dan audit trail.

Blocker aktif:

- Tidak ada blocker aktif yang diketahui.
- Warning build Vite tentang `/images/login-side.jpg` masih ada, tetapi tidak menghambat workflow.

## Akun Demo

### Andika

- NIK: `10494`
- Email: `andika.kuswidyarto@demo.eform-bp.invalid`
- Password: `password`
- Role: `employee`
- HOD: Heru
- Supervisor: tidak ada

### Heru

- NIK: `10220`
- Email: `heru.meireza@demo.eform-bp.invalid`
- Password: `password`
- Role: `employee`, `hod`

Seeder terkait: `database/seeders/AndikaHeruUserSeeder.php`

### Akun demo approver (semua form, domain korporat)

Password semua akun: `password`. Seeder: `database/seeders/DemoApproverSeeder.php`
(`php artisan db:seed --class=DemoApproverSeeder`, idempoten).

| Akun | Role | Approver di form |
|---|---|---|
| `demo.pemohon@borneoprima.com` | employee | Pemohon (supervisor + HOD terisi) |
| `demo.supervisor@borneoprima.com` | employee, supervisor | Cuti/Izin, Perjalanan Dinas |
| `demo.hod@borneoprima.com` | employee, hod | Cuti/Izin, Dinas, IT, ERP |
| `demo.pm@borneoprima.com` | employee, project_manager | Cuti/Izin, Dinas, IT/ERP (PM/GM site) |
| `demo.hrga@borneoprima.com` | employee, hrga | Cuti/Izin, Dinas, Settlement, Medical |
| `demo.hrgamanager@borneoprima.com` | employee, hrga_manager | Medical (validasi dokumen) |
| `demo.finance@borneoprima.com` | employee, finance | Settlement, Medical (pembayaran) |
| `demo.it@borneoprima.com` | employee, it | IT, ERP (+ penentu eskalasi COO/CEO) |
| `demo.erpreviewer@borneoprima.com` | employee, erp_reviewer | ERP Request |
| `demo.cooceo@borneoprima.com` | employee, coo, coo_ceo | IT (bila dieskalasi IT ke COO) |
| `demo.ceo@borneoprima.com` | employee, ceo | IT (bila dieskalasi IT ke CEO) |
| `demo.admin@borneoprima.com` | employee, admin | Master, workflow, role, user |
| `demo.auditor@borneoprima.com` | auditor | Lihat + laporan (read-only) |

## Perubahan Penting

### Authorization dan visibility

- `app/Services/EmployeeVisibility.php` mengatur employee yang dapat dilihat.
- Supervisor/HOD dapat melihat transaksi sendiri dan bawahan recursive.
- Permission subordinate:
  - `leave.view.subordinates`
  - `travel.view.subordinates`
  - `settlement.view.subordinates`
  - `medical.view.subordinates`
- Permission `leave.view.all` dan `travel.view.all` tidak lagi diberikan ke Supervisor/HOD.
- Bypass read melalui `travel.submit.onbehalf` sudah dihapus.
- Policy tetap menjadi kontrol authorization server-side.

### Eskalasi COO/CEO (IT Request Hardware & Software)

- Kebutuhan khusus (`special_specification`) tidak lagi langsung di-assign ke COO/CEO saat submit; step `coo_ceo` dibuat `Cancelled` (deferred, audit `approval.step_deferred`).
- Reviewer IT memutuskan saat approve tahap `it`: `escalation=none` atau `escalation=coo_ceo` dengan `escalate_role` (`coo`|`ceo`), `escalate_user_id`, dan `comments` wajib.
- Target harus aktif, punya `approval.act`, memegang role yang dipilih, tanpa konflik kepentingan, dan tidak sedang menunggu di chain yang sama.
- `approver_role` pada step `coo_ceo` diubah ke role terpilih (`coo`/`ceo`) agar `ApprovalRequestPolicy::act` tetap memeriksa role yang benar; role payung `coo_ceo` dipertahankan untuk kompatibilitas akun lama.
- Catatan eskalasi disimpan pada `approval_requests.comments` dan snapshot `approval_snapshot.coo_ceo.escalation_note`; halaman approval penerima menampilkan kartu amber berisi catatan, role tujuan, penulis eskalasi, deskripsi perangkat, dan tujuan penggunaan.
- Audit: `approval.escalation_decided` dengan properti `decision`, `escalate_role`, `escalation_note`, `approval_id`, `coo_approval_id`.

### Workflow UI

- Komponen reusable: `resources/js/Components/WorkflowStepper.jsx`
- Dipakai pada Cuti, Perjalanan Dinas, Settlement, dan Medical Claim.
- Mendukung tampilan horizontal, vertical, dan swimlane.
- PIC approval ditampilkan dari approval timeline.

### Form dan read-only

- View transaksi dibuat read-only/disabled sesuai status dan authorization.
- Spacing form utama diseragamkan menggunakan `space-y-6`.
- Role admin UI tersedia di `resources/js/Pages/Settings/Roles/`.

### PIC dan pending action

- PIC approval ditampilkan dari approval timeline.
- PIC proses advance Cuti/Perjalanan diambil dari actor audit `advance.*`.
- PIC proses pembayaran dan penyelesaian Medical Claim diambil dari actor audit `medical.payment_processing` dan `medical.completed`.
- Tahap `Selesai` pada status `completed` ditampilkan sebagai checklist.
- Dashboard menampilkan pending action sesuai permission dan ownership untuk approval, advance, settlement, serta pembayaran Medical Claim.
- User yang memproses pembayaran Medical Claim tidak dapat menandai claim yang sama sebagai selesai. Action tersebut harus dilakukan user lain yang memiliki `medical.payment.complete` atau Administrator.
- Detail approval Medical Claim menampilkan rincian pasien, tanggal berobat, fasilitas, nominal item, dan lampiran sesuai hak akses medis.

## Catatan Perjalanan Dinas

File terkait:

- `resources/js/Pages/Travels/Create.jsx`
- `resources/js/Pages/Travels/Edit.jsx`
- `resources/js/Pages/Travels/TravelForm.jsx`
- `resources/js/Components/Modal.jsx`
- `app/Http/Requests/StoreTravelRequestRequest.php`
- `app/Http/Controllers/TravelRequestController.php`
- `routes/web.php`

### Perubahan yang sudah dibuat

- `TravelForm.jsx` menggunakan `noValidate` agar validasi dikembalikan ke server.
- Tombol utama memakai `type="button"` dan membuka modal secara eksplisit.
- Tombol konfirmasi memanggil `onSubmit()`.
- Modal tidak langsung ditutup sebelum request selesai.
- Error server ditampilkan di dalam modal.
- Tombol dan modal dipindahkan keluar dari `<fieldset>` agar tidak ikut disabled.
- `Modal.jsx` diberi `z-index` eksplisit dan `pointer-events-auto` pada panel.

### Alur yang sudah diverifikasi

1. `TravelForm` membuka modal.
2. Tombol `Ya, simpan draf` memanggil `save` dari `Create.jsx` atau `Edit.jsx`.
3. `save` mengirim request Inertia tanpa chaining `transform(...).post(...)` yang tidak valid.
4. Server menyimpan status `Draft` dan redirect ke detail transaksi.

### Diagnosis jika regresi muncul

Jika blocker masih terjadi setelah hard refresh:

1. Buka DevTools browser, tab **Console** dan **Network**.
2. Klik `Ya, simpan draf`.
3. Periksa apakah ada request `POST /travels` atau `PUT /travels/{id}`.
4. Jika tidak ada request, periksa apakah tombol mempunyai atribut `disabled` dan apakah ada error JavaScript.
5. Jika ada request, catat HTTP status dan response validation/error.
6. Pastikan browser memakai asset terbaru. `public/hot` saat ini menunjuk ke `http://[::1]:5173`, sehingga browser menggunakan Vite dev server.

Periksa asset Vite/browser dan request Inertia melalui DevTools.

## Email Workflow (2026-10-20)

Setiap notifikasi database juga dikirim via email dengan skema produksi SMTP:

- **Pintu tunggal**: `InAppNotifier::notifyUser()` tetap satu tempat memutuskan penerima. Ia mengirim notifikasi database secara sinkron, lalu mem-dispatch job `App\Jobs\SendWorkflowEmail` (antrian `database`, `afterCommit`, `tries=3`, backoff 30/120 s) yang mengirim `App\Mail\WorkflowMail`.
- **Template**: `resources/views/mail/workflow.blade.php` — band perusahaan + chip chip "eForm BP", judul/pesan dari payload, metadata (Modul, Nomor dokumen, Pelaku, Tahap), tombol "Buka di eForm BP" memakai `config('app.url')` + path resolusi `InAppNotifier::resolveUrl`.
- **Guard** (dievaluasi di dalam job agar retry memakai preferensi terbaru): kolom `users.email_notifications` (default true), `config('eform.mail.enabled')` (env `MAIL_NOTIFICATIONS_ENABLED`), blacklist `config('eform.mail.skip_events')` = `approval.step_deferred`, `approval.chain_step_cancelled`, email kosong atau domain `.invalid` (akun demo).
- **Env**: `MAIL_MAILER=smtp`, `MAIL_FROM_ADDRESS=admin.it@borneoprima.com`, `MAIL_FROM_NAME="eForm BP"`. Kredensial SMTP host/port/user/password diisi saat deploy produksi; lokal pengembangan bisa kembali `MAIL_MAILER=log`.
- **Tes**: `tests/Feature/WorkflowEmailNotificationTest.php` — dispatch job pada setiap notifikasi, isi payload, skip opt-out sementara, skip blacklist, skip tanpa email/dummy, skip kill-switch. `phpunit.xml` tetap menggunakan `MAIL_MAILER=array` + `QUEUE_CONNECTION=sync` sehingga test tidak mengirim email nyata.

File terkait:

- `app/Jobs/SendWorkflowEmail.php`
- `app/Mail/WorkflowMail.php`
- `resources/views/mail/workflow.blade.php`
- `app/Services/InAppNotifier.php`
- `config/eform.php` (bagian `mail`)
- `database/migrations/2026_10_20_000002_add_email_notifications_to_users_table.php`
- `tests/Feature/WorkflowEmailNotificationTest.php`

## Verifikasi Terakhir

```text
MedicalClaimAuditTest: 11 passed (66 assertions)
DashboardApprovalTest + MedicalClaimAuditTest: 13 passed (90 assertions)
SettlementTest + MedicalClaimAuditTest: 34 passed (163 assertions)
npm run build: berhasil
git diff --check: berhasil
```

Build masih menampilkan warning `/images/login-side.jpg` yang belum resolve saat build time. Warning tersebut bukan blocker workflow.

## Perintah Umum

```powershell
php artisan test --filter=Travel --compact
npm run build
git diff --check
php artisan migrate:fresh --seed
```

Jalankan `migrate:fresh --seed` hanya pada database development karena menghapus data database.

## File Yang Perlu Dibaca Saat Melanjutkan

- `eform-bp-prd-v1.md`
- `eform-bp-ai-coding-handoff-v1.md`
- `AGENTS.md`
- `docs/PROGRESS-HANDOFF.md`
- `resources/js/Pages/Travels/TravelForm.jsx`
- `resources/js/Components/Modal.jsx`
- `resources/js/Pages/Travels/Create.jsx`
- `app/Http/Controllers/TravelRequestController.php`
- `tests/Feature/TravelRequestTest.php`
- `tests/Feature/EmployeeVisibilityTest.php`
