# eForm BP Development Handoff

Tanggal catatan: 25 September 2026

Dokumen ini adalah snapshot progress untuk melanjutkan development dari device lain.

## Status Utama

Implementasi sudah mencakup:

- Modul Cuti/Izin, Perjalanan Dinas, Settlement, dan Medical Claim.
- Workflow stepper reusable dengan status dan PIC approval.
- Form transaksi dan detail read-only.
- Role/permission management untuk admin.
- Pembatasan visibilitas transaksi berdasarkan ownership dan struktur bawahan.
- Seeder akun demo Andika dan Heru.
- Validasi server-side, kalkulasi advance, settlement, attachment private, dan audit trail.

Blocker aktif:

- Pada form Perjalanan Dinas, modal konfirmasi sudah muncul, tetapi tombol `Ya, simpan draf` masih tidak terlihat menjalankan aksi atau menutup modal di browser.
- Backend endpoint penyimpanan draft sudah terbukti lulus feature test.

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

### Workflow UI

- Komponen reusable: `resources/js/Components/WorkflowStepper.jsx`
- Dipakai pada Cuti, Perjalanan Dinas, Settlement, dan Medical Claim.
- Mendukung tampilan horizontal, vertical, dan swimlane.
- PIC approval ditampilkan dari approval timeline.

### Form dan read-only

- View transaksi dibuat read-only/disabled sesuai status dan authorization.
- Spacing form utama diseragamkan menggunakan `space-y-6`.
- Role admin UI tersedia di `resources/js/Pages/Settings/Roles/`.

## Blocker Perjalanan Dinas

File terkait:

- `resources/js/Pages/Travels/Create.jsx`
- `resources/js/Pages/Travels/Edit.jsx`
- `resources/js/Pages/Travels/TravelForm.jsx`
- `resources/js/Components/Modal.jsx`
- `app/Http/Requests/StoreTravelRequestRequest.php`
- `app/Http/Controllers/TravelRequestController.php`
- `routes/web.php`

### Perubahan terakhir yang sudah dibuat

- `TravelForm.jsx` menggunakan `noValidate` agar validasi dikembalikan ke server.
- Tombol utama memakai `type="button"` dan membuka modal secara eksplisit.
- Tombol konfirmasi memanggil `onSubmit()`.
- Modal tidak langsung ditutup sebelum request selesai.
- Error server ditampilkan di dalam modal.
- Tombol dan modal dipindahkan keluar dari `<fieldset>` agar tidak ikut disabled.
- `Modal.jsx` diberi `z-index` eksplisit dan `pointer-events-auto` pada panel.

### Alur yang diharapkan

1. `TravelForm` membuka modal.
2. Tombol `Ya, simpan draf` memanggil `save` dari `Create.jsx` atau `Edit.jsx`.
3. `save` menjalankan `transform(...).post(...)` atau `put(...)`.
4. Server menyimpan status `Draft` dan redirect ke detail transaksi.

### Diagnosis berikutnya

Jika blocker masih terjadi setelah hard refresh:

1. Buka DevTools browser, tab **Console** dan **Network**.
2. Klik `Ya, simpan draf`.
3. Periksa apakah ada request `POST /travels` atau `PUT /travels/{id}`.
4. Jika tidak ada request, periksa apakah tombol mempunyai atribut `disabled` dan apakah ada error JavaScript.
5. Jika ada request, catat HTTP status dan response validation/error.
6. Pastikan browser memakai asset terbaru. `public/hot` saat ini menunjuk ke `http://[::1]:5173`, sehingga browser menggunakan Vite dev server.

Kemungkinan penyebab yang belum terkonfirmasi:

- Asset Vite/browser masih stale.
- Tombol masih disabled karena state `processing`.
- Event click tertahan oleh runtime error atau Headless UI.
- Request terkirim tetapi response error tidak terlihat oleh user.

## Verifikasi Terakhir



```text
/images/login-side.jpg referenced in /images/login-side.jpg didn't resolve at build time
```

Warning tersebut bukan blocker workflow.

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
