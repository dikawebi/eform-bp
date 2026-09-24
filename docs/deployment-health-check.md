# Deployment dan health check eForm BP

Checklist shared hosting (PHP 8.3):

- `php -m` harus memuat `bcmath`, `pdo_mysql`, `fileinfo`, `mbstring`, dan `openssl`.
- Jalankan `composer validate --strict`, `php artisan migrate --force`, lalu `php artisan storage:link` **tidak diperlukan** untuk disk private. Pastikan `storage/app/private/eform` writable dan tidak berada di document root.
- Queue memakai database: jalankan `php artisan queue:work database --stop-when-empty --tries=3` dari cron (atau worker provider). Periksa `jobs` dan `failed_jobs`; jangan mengubah connection ke Redis pada MVP.
- Cron wajib menjalankan `php artisan schedule:run` setiap menit. Health check operasional: `php artisan queue:monitor database:default,100` bila scheduler tersedia.
- Set `MAIL_MAILER`, host, port, username, password, encryption, dan `MAIL_FROM_ADDRESS` SMTP. Uji `php artisan tinker` dengan notifikasi di staging, bukan data medis produksi.
- Verifikasi `php -v` adalah 8.3.x, `php -r "echo extension_loaded('bcmath') ? 'ok' : 'missing';"`, koneksi database, dan permission storage.
- Jalankan `php artisan about` dan `php artisan migrate:status` setelah deploy.

## Migration dan morph compatibility

`ApprovalRequest`, `Attachment`, `Settlement`, dan activity log menggunakan morph type. `AppServiceProvider` mendaftarkan morph map; jangan mengganti alias yang sudah tersimpan (`leave_request`, `travel_request`, `settlement`, `medical_claim`, `approval_request`, `attachment`, `user`). Jika aplikasi pernah berjalan tanpa morph map, audit data lama dan lakukan migration mapping secara terkontrol sebelum mengaktifkan constraint baru. Unique `medical_claims.payment_reference` dipertahankan untuk mencegah pembayaran ganda; deploy migration ini setelah membersihkan duplikat.

Jalankan smoke check authenticated untuk dashboard, `/api/dashboard/summary`, `/reports`, audit timeline, private attachment download, queue, dan SMTP. Endpoint reports hanya mengembalikan allowlist DTO; nominal/detail medis tidak diberikan kepada role aggregate/auditor.
