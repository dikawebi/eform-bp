# Changelog dokumentasi

## 2026-10-19

- Eskalasi IT Request kini memilih tujuan secara eksplisit: **COO** atau **CEO** (role baru `coo` dan `ceo`, permission sama dengan role payung lama `coo_ceo`). Field `escalate_role` wajib saat `escalation=coo_ceo`; target harus memegang role yang dipilih, aktif, punya `approval.act`, tanpa konflik, dan tidak sedang menunggu di chain yang sama. `approver_role` tahap `coo_ceo` dipersempit ke role terpilih agar policy `act` tetap ketat.
- Catatan eskalasi wajib diisi reviewer IT dan disimpan pada tahap COO/CEO. Halaman approval penerima menampilkan kartu amber "Catatan eskalasi dari IT" berisi role tujuan, penulis eskalasi, catatan, deskripsi perangkat, dan tujuan penggunaan sehingga approver tidak perlu menelusuri dokumen.
- Modal approval IT menampilkan radio COO/CEO (dengan sublabel jabatan), dropdown penerima yang difilter sesuai role, dan textarea catatan bergaya highlight. Validasi dan audit (`escalate_role`, `escalation_note`) ikut dicatat.
- `DemoApproverSeeder` menambah `demo.ceo@borneoprima.com`; `demo.cooceo@borneoprima.com` kini memegang role `coo` + `coo_ceo` sehingga lolos validasi eskalasi. Total akun demo menjadi 13.
- Label role `coo` dan `ceo` ditambahkan pada inbox approval, detail approval, dan metadata notifikasi.

## 2026-10-13

- Seeder demo approver baru `DemoApproverSeeder`: 12 akun @borneoprima.com (password `password`) mencakup seluruh approver — supervisor, HOD, PM, HRGA, HRGA Manager, Finance, IT, Reviewer ERP, COO/CEO — plus pemohon, admin, auditor. Masing-masing terhubung ke master employee aktif, relasi supervisor/HOD pemohon dan mapping site→PM/GM ikut disiapkan untuk uji manual IT/ERP. Idempoten; dijalankan via `php artisan db:seed --class=DemoApproverSeeder`.
- Notifikasi lebih detail dan deep-link benar: payload membawa modul, nomor dokumen, pelaku, dan tahap (`InAppNotifier::context`); URL notifikasi IT/ERP kini mengarah ke detail transaksinya (sebelumnya jatuh ke dashboard). Bell dan halaman Notifikasi menampilkan badge jenis kejadian + baris dokumen/pelaku; klik tetap menandai dibaca lalu membuka data pending.
- Requester dapat menarik kembali (withdraw) pengajuan hingga status Approved selama belum ada uang bergerak: Cuti/Dinas s.d. Approved (bukan AdvancePaid/SettlementRequired), Settlement s.d. InReview, Medical s.d. Approved, IT/ERP s.d. InReview. Service `WithdrawRequest` membatalkan tahap pending, mempertahankan riwayat Approved, mencatat alasan + jumlah approval terdahulu di audit, dan memberi tahu approver aktif. Tombol Batal tampil otomatis via policy; dialog konfirmasi memperingatkan saat posisi sedang review.
- Perbaiki klik notifikasi yang selalu jatuh ke dashboard: URL tersimpan berbentuk absolut (`route()`), sedangkan pembaca hanya menerima path relatif. `InAppNotifier::resolveUrl()` kini mengurai path (+query) dari URL absolut sehingga klik membuka data pending yang benar; berlaku juga untuk notifikasi lama.
- Pilihan visualisasi form approval IT & ERP: tab Ringkasan/Formulir (formulir asli read-only tertanam), stepper grafis rantai tahap dengan nama approver, dan tombol Cetak (sidebar/topbar/panel aksi disembunyikan saat cetak). Payload `ApprovalViewData` kini membawa `chain`, `form`, dan `form_meta` untuk kedua tipe.
- Kontrol modul & maintenance khusus admin (`Pengaturan → Modul & Maintenance`, permission baru `module.manage` otomatis milik admin): 7 modul (Cuti, Dinas, Settlement, Medical, IT, ERP, Laporan) dapat dimatikan dengan pesan maintenance kustom. Member yang membuka modul mati mendapat halaman 503 (GET) / 503 (aksi tulis), menu modul hilang, dan pemegang `module.manage` tetap lolos untuk verifikasi. Middleware `module:*`, tabel `module_availabilities` (default aktif), dan audit `module.updated`.
- Format cetak serah terima ke Commercial sebagai dasar PR (route `it-requests.print`, khusus status completed): Memo Penerusan (kop, penerima, tabel item, dasar persetujuan, kolom tanda tangan), Daftar Item (tabel PR + bukti approval), dan Formulir Lengkap. Tombol Cetak untuk Commercial + modal pilih format di detail IT; authorization ikut policy view, format lain/belum completed ditolak.
- Sapaan dasbor memakai nama karyawan dari master (`employeeName`); jatuh ke nama akun bila pengguna belum tertaut NIK.
- Dropdown karyawan Bagian 01 form IT/ERP: panel opsi tidak lagi terpotong (`overflow` section), tampil bersama grid identitas, plus ikon pencarian/chevron, highlight hover, sublabel departemen · jabatan, dan counter hasil. Mencentang "karyawan baru" mengosongkan `employee_id` agar tidak tersimpan penerima master yang salah.
- Daftar IT Request dan ERP Request kini searchable, filter, dan sortable langsung di header tabel: baris judul untuk sort (nomor, jenis/tipe, status, tanggal + kolom Tanggal baru), baris kedua untuk pencarian nomor/penerima (Enter) dan dropdown filter, plus Atur ulang saat filter aktif. Server-side mengikuti pola modul Cuti.
- Filter Perangkat multi-select berkotak-cari di header IT (bisa pilih beberapa perangkat sekaligus); pencarian global juga mencakup tipe perangkat. Filter Tanggal mendukung satu tanggal atau range (dari–sampai) di kedua daftar; validasi allowlist + urutan tanggal di server.

- IT Request kebutuhan khusus: step COO/CEO tidak lagi langsung di-assign saat submit. Step dibuat tanpa approver (deferred, audit `approval.step_deferred`); reviewer IT wajib memutuskan saat approve: eskalasi ke COO/CEO tertentu atau tidak perlu eskalasi (audit `approval.escalation_decided`). Scope hanya modul Hardware & Software Request; ERP tidak berubah.
- Modal approval tahap IT menampilkan pilihan eskalasi + dropdown target COO/CEO yang eligible (role `coo_ceo`, tanpa konflik).
- Teks bantuan kebutuhan khusus di form diselaraskan: IT menilai dan menentukan eskalasi saat review.

## 2026-10-08

- Permintaan penautan akun ke NIK menjadi pending action dashboard (`Tautkan Akun ke NIK`) untuk pemegang `user.manage`; user tanpa NIK dapat mengirim permintaan dari halaman Unlinked (throttle + notifikasi admin/HRGA).
- Halaman Buat Cuti, Perjalanan Dinas, dan Settlement menampilkan panduan Unlinked bila akun belum tertaut NIK aktif (sebelumnya form kosong/gagal saat simpan); pemegang hak on-behalf tetap mendapat form.
- Menambahkan permission `settlement.create.onbehalf` yang selama ini dirujuk controller tetapi belum ada di seeder (untuk hrga_manager dan admin).

- Halaman Buat Cuti, Perjalanan Dinas, dan Settlement menampilkan panduan Unlinked bila akun belum tertaut NIK aktif (sebelumnya form kosong/gagal saat simpan); pemegang hak on-behalf tetap mendapat form.
- Menambahkan permission `settlement.create.onbehalf` yang selama ini dirujuk controller tetapi belum ada di seeder (untuk hrga_manager dan admin).

## 2026-10-07

- Menambahkan modul IT Request (`it_requests`): barang baru/penggantian, satu perangkat utama, accessories multi-select, software standar read-only + opsional custom, kebutuhan khusus dinilai IT saat review (eskalasi ke COO/CEO atau tidak — lihat catatan 2026-10-13).
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
