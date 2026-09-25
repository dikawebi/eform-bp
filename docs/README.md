# eForm BP — dokumentasi implementasi

Dokumen ini mencatat perilaku yang benar-benar ada di repository ini. PRD v1.1 dan AI Coding Handoff v1.1 tetap menjadi sumber kebutuhan; keterangan **Gap** berarti kebutuhan tersebut belum terlihat sebagai implementasi pada source saat dokumentasi dibuat.

Panduan visual form transaksi untuk pengembangan selanjutnya: [Panduan styling form](panduan-styling-form.md).

## 1. Stack dan Phase 0–7

| Phase | Implementasi aktual |
|---|---|
| 0 Foundation | Laravel 12, PHP `^8.3`, React/Inertia, Vite, Tailwind, Spatie Permission dan Activitylog, database session/cache/queue, private filesystem, policy dan layout aplikasi. |
| 1 Master Karyawan | `employees`, CRUD/import dan relasi supervisor/HOD; transaksi menyimpan employee snapshot ketika submit. |
| 2 Cuti/Izin | `leave_requests`, periode dinamis, hitung hari inklusif server-side, biaya lokal/non-lokal, draft/submit/return/reject/approval, audit. |
| 3 Perjalanan Dinas | `travel_requests` dan item biaya dinamis; total advance dihitung server-side; penanda project menentukan step PM. |
| 4 Approval Engine | Workflow dan step tersimpan di database; chain dibuat saat submit; approve/reject/return/delegate dan audit. |
| 5 Advance/Settlement | Settlement dari cuti atau dinas, satu settlement final per source, rekonsiliasi dan review HRGA/Finance. Lampiran settlement private. |
| 6 Medical Claim | Klaim untuk employee/istri/anak melalui item pasien, benefit dan dokumen; review HRGA serta maker-checker pembayaran. |
| 7 Hardening | Policy test, validasi upload, private download, morph compatibility migration, audit timeline, dan konfigurasi deployment shared hosting. |

Modul di luar MVP—D365, payroll otomatis, mobile native, WebSocket dan OCR—tidak tersedia.

## 2. Setup lokal

Prasyarat: PHP 8.3 dengan ekstensi **BCMath**, Composer, Node.js/npm, dan MySQL 8 atau MariaDB 10.6+. SQLite masih menjadi default file `.env.example`, tetapi environment yang menyerupai deployment sebaiknya menggunakan MySQL/MariaDB.

```bash
composer install
copy .env.example .env       # Windows; gunakan cp pada Linux/macOS
php artisan key:generate
# isi DB_*, APP_URL, dan kredensial mail pada .env
php artisan migrate --seed
npm install
npm run build                # atau npm run dev untuk development
php artisan serve
php artisan queue:work database
```

`DatabaseSeeder` membuat role/permission, workflow default, dan administrator. Pada production `ADMIN_INITIAL_PASSWORD` wajib diisi; di non-production password admin acak dibuat jika kosong. Ganti password setelah login.

### Environment penting

```dotenv
APP_ENV=local
APP_DEBUG=true              # hanya lokal; production wajib false
APP_URL=http://localhost
APP_KEY=...
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eform_bp
DB_USERNAME=...
DB_PASSWORD=...
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
MAIL_MAILER=log             # smtp di server
```

`config/eform.php` saat ini menetapkan tipe cuti `annual_leave`, `roster_leave`, `coff`, `permission`, `sick`, `other`; kategori biaya cuti `land_transport`, `hotel`, `meal`, `other`; source settlement eligible `advance_paid` atau `settlement_required`; partial settlement false. Medical menerima `receipt`, `prescription`, `doctor_letter`, wajib `receipt`, maksimum 5120 KB.

## 3. Role dan permission

Role yang diseed: `employee`, `supervisor`, `hod`, `project_manager`, `hrga`, `hrga_manager`, `finance`, `admin`, `auditor`. Authorization selalu melalui Policy/Gate dan permission Spatie; nama jabatan bebas tidak menjadi kontrol akses.

- **Employee**: dashboard, data sendiri, membuat/melihat cuti, dinas, settlement, medical claim sendiri, upload/download lampiran sendiri.
- **Supervisor/HOD/Project Manager**: melihat transaksi sesuai scope approval, inbox dan `approval.act`, lampiran assigned. PM dipakai sebagai step project.
- **HRGA**: master employee, administrasi transaksi, settlement review HRGA, review medical dan akses dokumen medis sensitif.
- **HRGA Manager**: hak HRGA ditambah `workflow.manage`, pembuatan/submit beberapa transaksi atas nama dan export.
- **Finance**: review/complete settlement, proses/complete payment medical, aggregate medical; role ini juga seeded dengan `leave.view.all` dan `travel.view.all`.
- **Admin**: seluruh permission yang didefinisikan seeder.
- **Auditor**: baca dan export/audit; tidak memiliki `attachment.download.medical` dan tidak melihat detail medis sensitif.

Permission seeded mencakup `leave.*`, `travel.*`, `settlement.*`, `medical.*`, `approval.*`, `attachment.*`, `workflow.manage`, dan `report.export`. Employee/approver hanya dapat mengakses objek yang lolos policy ownership atau assignment; tombol UI bukan authorization.

## 4. Workflow dan status

Enum `RequestStatus` digunakan pada request, settlement, dan medical claim: `draft`, `submitted`, `in_review`, `returned`, `rejected`, `approved`, `processing`, `advance_paid`, `settlement_required`, `payment_processing`, `completed`, `cancelled`.

Submit hanya menerima `draft` atau `returned`. Submit mengunci row, memastikan employee aktif, menghitung ulang, menyimpan snapshot, membuat approval chain, lalu mengubah status menjadi `in_review`. Approved tidak diedit langsung; revisi harus melalui returned flow. Approval action dan status transition dicatat Activitylog.

Workflow default tersimpan sebagai `leave_default`, `travel_default`, `settlement_default`, dan `medical_default`:

- Cuti/dinas: supervisor (boleh skip jika tidak ada), HOD, PM bila `is_project_trip`, lalu HRGA. Resolver dan role berasal dari tabel workflow, bukan controller.
- Settlement: HRGA lalu Finance.
- Medical: HRGA lalu `document_validation` oleh HRGA Manager.

Approver dipilih dari snapshot supervisor/HOD/PM atau active users dengan role, permission `approval.act`, employee aktif; self-approval/conflict ditolak. Step tidak aktif diberi `cancelled`/`skipped` sesuai chain.

### Cuti/Izin

`leave_periods` memakai kategori `onsite`, `travel_home`, `roster_leave`, `coff`, `annual_leave`, `permission`, `travel_to_site`. Hari dihitung inklusif (`end - start + 1`) per periode; `onsite` sebelum cuti, hari terakhir kerja, dan onsite setelah cuti adalah penanda tanggal dan tidak dijumlahkan ke `total_days`. `leave_cost_items.amount = quantity × unit_price`; untuk karyawan lokal amount selalu nol dan `eligible_by_policy=false`, sedangkan non-lokal eligible. Form Cuti hanya membuka Travel, Penginapan, dan Makan untuk item baru; data kategori `other` historis tetap dipertahankan. Semua nilai dihitung ulang server-side ketika submit.

### Perjalanan Dinas

Item Dinas memakai `land_transport`, `flight`, `hotel`, `meal`, `other`; flight hanya tersedia pada dinas, bukan item biaya cuti, dan dicatat tanpa nominal sehingga tidak menambah advance. `amount` dan `total_advance` dihitung dari item tersimpan menggunakan BCMath. Snapshot employee dan approval ikut disimpan.

### Settlement

Source hanya `leave_request` atau `travel_request`, melalui pasangan `source_type`/`source_id` dan FK nullable yang sesuai. Source harus berstatus `advance_paid` atau `settlement_required`, employee aktif, dan `total_advance > 0`. `source_key` (`leave_request:ID` atau `travel_request:ID`) unique nullable memastikan satu settlement aktif/final; `allow_partial=false`. Settlement cancelled mengosongkan `source_key`, sehingga dapat dibuat pengganti.

`actual_amount` adalah jumlah item settlement; `difference_amount = advance - actual`:

- `overpayment`: advance lebih besar, dikembalikan ke perusahaan;
- `underpayment`: actual lebih besar, dibayar ke employee;
- `balanced`: sama.

Pembuatan dan penyelesaian menggunakan transaction serta row lock. Complete hanya oleh Finance yang memiliki approval Finance; settlement dan source diubah ke `completed` secara atomic dan keduanya diaudit.

### Medical Claim

Benefit yang dikonfigurasi: `rawat_jalan`, `rawat_inap`, `kacamata`, `persalinan`, `lainnya`. Item menyimpan `patient_name`, `relationship`, tanggal, fasilitas, dan nominal. Submit wajib memiliki attachment `receipt`; total dihitung dari item. `medical.view.aggregate`/`medical.view.all` tidak sama dengan akses sensitive.

## 5. Database dan kontrak antar layer

Header transaksi menyimpan foreign key `employee_id`, status, total server-side, timestamps, creator/updater, dan snapshot employee. React/Inertia hanya mengirim input; Form Request memvalidasi, Policy mengotorisasi, service/action menghitung dan mengubah status dalam transaction, controller mengembalikan props Inertia. Jangan menjadikan total/status dari client sebagai sumber kebenaran.

Tabel utama:

- `leave_requests`: nomor, employee, `leave_type`, alasan, tanggal penting, `total_days`, `total_advance`, `is_local`, status, snapshot.
- `leave_periods`: `leave_request_id`, category, start/end, `day_count`, notes.
- `leave_cost_items`: request, category, description, quantity, unit price, amount, `eligible_by_policy`.
- `travel_requests`/`travel_request_items`: tujuan/tanggal/project flag dan item biaya dengan metadata.
- `settlements`: `settlement_number`, `source_type` enum, `source_id`, `source_key`, FK `leave_request_id`/`travel_request_id`, employee, status, advance/actual/difference, `difference_type`, final/partial flag, snapshot.
- `settlement_items`: tanggal, deskripsi, kategori `transport|hotel|meal|other`, amount, receipt number.
- `medical_claims`/`medical_claim_items`: claim, benefit, total, snapshot, pasien/relationship/tanggal/fasilitas/nominal.
- `attachments`: polymorphic `attachable_type`/`attachable_id`, document metadata, `stored_path`, MIME, size, SHA-256, uploader.
- Approval: `approval_workflows`, `approval_workflow_steps`, `approval_requests`, `approval_actions`.

### Morph compatibility

`AppServiceProvider` mendaftarkan morphMap alias: `leave_request`, `travel_request`, `settlement`, `medical_claim`, `approval_request`, `attachment`, `user`. Migration `2026_09_23_170000_normalize_morph_types` mengubah FQCN lama ke alias pada `approval_requests`, `attachments`, dan Activitylog. Nilai morph yang tidak dikenal sengaja menyebabkan migration gagal dan harus dibereskan dahulu. Rollback migration ini no-op karena alias baru tidak dapat dipulihkan aman ke FQCN. Jalankan backup dan cek data sebelum `migrate` pada database lama.

## 6. Attachment dan privasi medical

Disk transaksi adalah `eform-private` di `storage/app/private/eform`, visibility private, tanpa URL public. Download selalu melalui route/controller setelah `AttachmentPolicy`; ID attachment saja tidak cukup. File settlement disimpan pada folder settlement dan dihapus bila transaksi upload gagal. Upload menyimpan MIME, extension tervalidasi, ukuran, nama asli, SHA-256, dan audit.

Medical hanya menerima MIME/extension `pdf,jpg,jpeg,png,webp`, maksimum 5120 KB, dan dokumen `receipt|prescription|doctor_letter`. Pemilik dapat melihat data sendiri; HRGA dengan `medical.view.sensitive` + `medical.review` dapat meninjau; Finance/Auditor seeded hanya mendapat aggregate/payment sesuai permission. Dokumen medical tidak boleh dipindah ke public disk atau disajikan sebagai public URL.

## 7. Queue, cron, SMTP, dan shared hosting

Queue default adalah database (`QUEUE_CONNECTION=database`), dengan tabel `jobs`, `job_batches`, dan `failed_jobs` dari migration. MVP tidak membutuhkan Redis, daemon permanen, Reverb, atau WebSocket. Jika ada job di masa depan, jalankan worker hosting dengan mekanisme provider atau cron yang memanggil worker terbatas, lalu pantau `failed_jobs`.

`routes/console.php` saat ini hanya berisi command `inspire`; belum ada task scheduler aplikasi. Karena itu cron `schedule:run` belum menjalankan pekerjaan bisnis apa pun, tetapi checklist deployment tetap dapat memasangnya untuk kesiapan:

```cron
* * * * * cd /path/eform-bp && php artisan schedule:run >> /dev/null 2>&1
```

SMTP production harus diisi: `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, dan bila perlu `MAIL_SCHEME`. Nilai default `.env.example` adalah mailer `log`, bukan pengiriman email nyata. Tidak ada Notification/Job bisnis khusus yang ditemukan selain email verifikasi Laravel.

BCMath adalah dependency wajib Composer (`ext-bcmath`) dan dipakai untuk nominal/rekonsiliasi. Pastikan extension aktif pada PHP CLI dan PHP-FPM/hosting, bukan hanya salah satunya. PHP production harus 8.3 kompatibel.

## 8. Deployment checklist

1. Backup database dan `storage/app/private`; simpan `.env` di luar repository/akses web.
2. Pastikan PHP 8.3, BCMath, MySQL/MariaDB, Composer dan Node/npm tersedia saat build (atau upload artefak build).
3. `composer install --no-dev --optimize-autoloader`; `npm ci && npm run build`.
4. Arahkan document root ke folder `public`. Jika shared hosting hanya memberi `public_html`, salin isi `public` dan sesuaikan `index.php` ke path aplikasi—jangan expose root project, `.env`, `storage`, `vendor` secara langsung.
5. Isi `APP_KEY`, `APP_URL` HTTPS, database, SMTP, `FILESYSTEM_DISK=local`/disk private, `QUEUE_CONNECTION=database`, `SESSION_DRIVER` dan `CACHE_STORE` yang tersedia.
6. Jalankan `php artisan migrate --force` lalu `php artisan db:seed --force` hanya pada instalasi yang memang memerlukan seed. Production wajib `ADMIN_INITIAL_PASSWORD` saat seed awal.
7. Pastikan `storage/app/private/eform` writable oleh user web; **jangan** membuat symlink public untuk disk private. `storage:link` hanya untuk aset public bila memang digunakan.
8. Jalankan `php artisan optimize` setelah `.env` final; clear/rebuild cache bila mengubah konfigurasi.
9. Pasang cron scheduler di atas; siapkan worker database hanya jika ada job pending.
10. Aktifkan SSL dan paksa HTTPS pada hosting; `APP_URL` harus HTTPS. Uji login, policy ownership, approval, authorized download, upload invalid, queue dan SMTP.
11. Jadwalkan backup database dan private storage, retensi, serta uji restore. Backup harus terenkripsi dan tidak diletakkan di document root.
12. Set `APP_DEBUG=false` di production. Debug true dapat membocorkan stack trace, konfigurasi, path dan data sensitif.

## 9. Gap dan residual risk aktual

- `routes/console.php` belum memiliki task terjadwal bisnis dan tidak ada Job/Notification approval/settlement khusus; queue/cron adalah plumbing, bukan fitur notifikasi otomatis.
- Tidak ada migrasi/model `settlement.create.onbehalf` atau `leave.submit.onbehalf` pada daftar permission seeder, walaupun sebagian service memeriksa nama permission tersebut. Pembuatan/submit atas nama karena itu tidak tersedia melalui role seeded kecuali jalur owner yang lolos policy. Ini perlu keputusan/penambahan permission sebelum dijanjikan sebagai fitur.
- `finance` masih mendapat `leave.view.all` dan `travel.view.all` secara seeded; ini dicatat sebagai scope luas Phase 0 dan perlu dipersempit bila kebijakan least privilege diwajibkan.
- Scheduler/worker production, SMTP provider, backup provider, document root dan SSL adalah tanggung jawab deployment; belum dapat diverifikasi dari repository.
- Validasi benefit/limit kebijakan medical selain daftar benefit, dokumen wajib, nominal item dan akses role belum tampak sebagai konfigurasi limit; jangan mendokumentasikan benefit cap sebagai fitur tersedia.

## 10. Verifikasi cepat

```bash
php artisan migrate:status
php artisan test
npm run build
php artisan queue:failed
php artisan about
```

Uji manual minimal: employee hanya melihat transaksi sendiri; approver hanya chain yang ditugaskan; self-approval ditolak; returned dapat diedit; approved tidak; source settlement cuti dan dinas eligible; satu source tidak dapat settlement aktif kedua; tiga `difference_type`; medical attachment tidak dapat diunduh tanpa permission; dan setiap transition/action muncul di Activitylog.
