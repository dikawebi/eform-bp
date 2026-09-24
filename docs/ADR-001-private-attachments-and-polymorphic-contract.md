# ADR-001: private attachment dan kontrak polymorphic

- **Status:** Accepted
- **Tanggal:** 2026-09-23

## Keputusan

Lampiran disimpan pada disk `eform-private` di bawah `storage/app/private/eform`, bukan public URL. Akses memakai controller download dan `AttachmentPolicy` yang memeriksa parent polymorphic, ownership/assignment, dan permission sensitif medical.

Relasi polymorphic memakai alias stabil melalui `Relation::morphMap`: `leave_request`, `travel_request`, `settlement`, `medical_claim`, `approval_request`, `attachment`, dan `user`. `attachments.attachable_type` serta `approval_requests.approvable_type` karena itu menyimpan alias, bukan FQCN.

Settlement memakai kontrak ganda yang konsisten: `source_type` + `source_id` sebagai sumber generik, FK `leave_request_id` atau `travel_request_id` sebagai referensi eksplisit, dan `source_key` unique nullable untuk mencegah duplicate settlement aktif. Resolusi source wajib mencocokkan keempat nilai tersebut serta employee.

## Konsekuensi dan verifikasi

Migration normalisasi morph harus dijalankan setelah backup. Nilai morph tak dikenal menyebabkan migration gagal; rollback migration sengaja no-op. Perubahan akses attachment wajib diuji melalui policy, bukan hanya UI. Unique `source_key` dan row lock harus dipertahankan ketika mengubah flow settlement.
