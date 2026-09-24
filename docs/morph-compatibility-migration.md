# Deployment: normalisasi morph type

Migration `2026_09_23_170000_normalize_morph_types` mengubah FQCN legacy yang
dikenal menjadi alias `morphMap` pada `approval_requests`, `attachments`, dan
`activity_log` (jika tabel/kolom tersedia). Migration aman dijalankan ulang.

Sebelum deploy, backup database lalu jalankan `php artisan migrate --force`.
Jika muncul error `unknown ... values`, migration berhenti dan mencatat nilai,
tabel, serta kolom ke log Laravel. Perbaiki atau mapping data orphan tersebut,
lalu jalankan migration kembali; jangan mengabaikan error karena record dapat
menjadi tidak dapat di-resolve oleh Eloquent.

Rollback migration ini adalah no-op. Alias tidak dikembalikan ke FQCN karena
database tidak menyimpan informasi apakah alias ditulis sebelum atau sesudah
migration. Untuk rollback aplikasi, pertahankan alias dan morphMap kompatibel,
atau lakukan backup restore/skrip perubahan yang telah direview.
