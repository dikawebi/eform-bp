# Changelog dokumentasi

## 2026-10-08

- Permintaan penautan akun ke NIK menjadi pending action dashboard (`Tautkan Akun ke NIK`) untuk pemegang `user.manage`; user tanpa NIK dapat mengirim permintaan dari halaman Unlinked (throttle + notifikasi admin/HRGA).
- Halaman Buat Cuti, Perjalanan Dinas, dan Settlement menampilkan panduan Unlinked bila akun belum tertaut NIK aktif (sebelumnya form kosong/gagal saat simpan); pemegang hak on-behalf tetap mendapat form.
- Menambahkan permission `settlement.create.onbehalf` yang selama ini dirujuk controller tetapi belum ada di seeder (untuk hrga_manager dan admin).

- Halaman Buat Cuti, Perjalanan Dinas, dan Settlement menampilkan panduan Unlinked bila akun belum tertaut NIK aktif (sebelumnya form kosong/gagal saat simpan); pemegang hak on-behalf tetap mendapat form.
- Menambahkan permission `settlement.create.onbehalf` yang selama ini dirujuk controller tetapi belum ada di seeder (untuk hrga_manager dan admin).

## 2026-10-07

- Menambahkan modul IT Request (`it_requests`): barang baru/penggantian, satu perangkat utama, accessories multi-select, software standar read-only + opsional custom, kebutuhan khusus memicu step COO/CEO.
- Menambahkan modul ERP Request (`erp_requests`): akun baru / perubahan role / reset akses D365 F&O, modul opsional, username ERP wajib untuk non-akun-baru, step Reviewer ERP.
- Alur approval: HOD → IT → PM/GM (site-based via `site_pm_gm_assignments`) → COO/CEO (kondisional); ERP menyisipkan Reviewer ERP sebelum IT.
- Kolom `site`/`cost_code` di employees; role baru `it`, `erp_reviewer`, `coo_ceo`; workflow `it_default`, `erp_default`.
- Menu sidebar IT Request dan ERP Request; renderer detail approval untuk kedua tipe.

## 2026-09-28

- Memperbarui handoff dengan status implementasi terakhir dan menghapus blocker Perjalanan Dinas yang sudah terselesaikan.
- Mendokumentasikan pending action berbasis permission untuk approval, advance, settlement, dan pembayaran Medical Claim.
- Mendokumentasikan PIC approval/proses dari approval timeline dan audit actor.
- Mendokumentasikan pencegahan user yang sama untuk memproses dan menyelesaikan pembayaran Medical Claim.
- Mendokumentasikan perbaikan stepper agar status `completed` menampilkan checklist pada tahap `Selesai`.

## 2026-09-23

- Menambahkan dokumentasi implementasi Phase 0–7 berdasarkan source aktual.
- Menjelaskan setup lokal, environment, role/permission, workflow, status, database, settlement `source_type`, morph compatibility migration, private attachment, medical privacy, queue/cron/SMTP/BCMath, backup dan deployment shared hosting.
- Mencatat gap aktual: scheduler bisnis dan notifikasi/job khusus belum ada, permission on-behalf tertentu belum diseed, serta scope Finance yang masih luas.
