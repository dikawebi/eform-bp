# Changelog dokumentasi

## 2026-10-23

- **Spesifikasi teknis IT Request ditetapkan oleh IT:** tabel baru `it_spec_templates` dan `it_request_specifications`. Reviewer IT dapat menyimpan draft spesifikasi (baris fleksibel `label + value`, catatan teknis, dan template internal) pada tahap `it`, lalu Approve terpisah. Approve tahap IT ditolak server-side bila spesifikasi belum tersimpan. Spesifikasi dikunci sebagai snapshot saat IT approve dan baru terlihat oleh pemohon/approver berikutnya. IT tidak dapat mengubah jenis perangkat; request harus dikembalikan kepada pemohon jika tidak sesuai. Audit baru: `it.specification_saved`, `it.specification_locked`, `it.spec_template.created`, `it.spec_template.updated`.
- **Master Template Spesifikasi IT:** halaman `master/it-spec-templates` dengan permission `it.master.manage`, mendukung template per jenis perangkat, urutan, status aktif, dan baris spesifikasi. Template hanya membantu pengisian; nilai final tetap disimpan sebagai snapshot pada request.
- **Seeder template spesifikasi IT:** `ItSpecTemplateSeeder` mengisi 5 template awal: Laptop Standard Office, Laptop High Spec, Desktop Standard Office, Desktop High Spec, dan Upgrade Berdasarkan Assessment IT. Seeder idempoten (`firstOrCreate`) dan terdaftar di `FoundationSeeder`, sehingga editan admin tidak tertimpa saat dijalankan ulang.
- **Tampilan dan PDF:** halaman approval IT menampilkan editor spesifikasi dan peringatan sebelum Approve; halaman detail IT Request menampilkan spesifikasi read-only setelah IT approve. PDF IT Request mendapat section **Spesifikasi Teknis Ditetapkan IT**; request lama tanpa spesifikasi menampilkan keterangan bahwa data belum tersedia.
- **Test:** `ItSpecificationTest` mencakup draft, authorization, validasi, lock saat approve, snapshot template, audit, dan PDF. Seluruh alur approval IT lama disesuaikan agar menyimpan spesifikasi sebelum approve.

## 2026-10-21

- **Notifikasi IT siap membuat PR setelah approval IT Request selesai:** saat seluruh tahap approval Hardware & Software selesai (`completed`), `NotifyItRequestPrReady` mengirim notifikasi in-app dan email ke seluruh user role `it` yang aktif, memiliki `it_request.review`, dan tertaut karyawan aktif. Event baru `it.pr_ready` dapat diatur di Pengaturan > Email > Template. Email menyertakan lampiran PDF dokumen IT Request (format yang sama dengan halaman Save to PDF) melalui dukungan attachment pada `InAppNotifier`/`SendWorkflowEmail`/`WorkflowMail`; file disimpan private di `eform-private` (`it-requests/pr-ready/{nomor}.pdf`). PDF dibuat oleh `ItRequestPdf` dan header dokumen kini memuat logo perusahaan. Bila pembuatan PDF gagal, notifikasi tetap dikirim tanpa lampiran dan kegagalan dicatat di log.
- **Stepper progress approval di email notifikasi:** email `mail/workflow` kini menampilkan daftar tahap persetujuan beserta status (Disetujui, Menunggu, Ditolak, Dikembalikan, Dibatalkan), nama approver, dan tanggal aksi. Data disusun dari rantai `approval_requests` terbaru saat job `SendWorkflowEmail` berjalan (setelah commit) melalui `InAppNotifier::approvalProgress()`, sehingga seluruh tahap sudah tersimpan. Tahap setelah approver aktif yang masih bertanda `cancelled` ditampilkan sebagai **Menunggu**; rantai yang benar-benar berhenti tetap **Dibatalkan**. Referensi dokumen (`approvable_type`/`approvable_id`) ditambahkan ke metadata notifikasi, tetapi tidak ditampilkan di kartu meta email maupun in-app.
- **Struktur organisasi karyawan per departemen:** menu Karyawan memiliki tombol **Struktur organisasi** ke `master/employees/org-chart`. Grafik ditampilkan sebagai pohon hierarki top-down **terpisah untuk setiap departemen** berdasarkan `supervisor_id` (atasan langsung), lengkap dengan garis penghubung, kartu ringkas (NIK, jabatan, departemen, level, HOD, jumlah bawahan), cabang buka/tutup, pencarian, pilihan departemen, filter status aktif/nonaktif, serta perluas/tutup semua. Atasan di luar departemen atau tanpa atasan aktif menjadi puncak grup; relasi melingkar tetap ditampilkan sebagai akar agar tidak hilang. Akses memakai `viewAny` Employee.
- **Template email digabung ke Pengaturan → Email & Notifikasi:** halaman terpisah `Settings/Mail/Templates` dihapus; sub-tab **SMTP** dan **Template Isi Email** kini berada dalam satu halaman per modul. Prop `templates` dikirim dari `MailSettingsController@index`.
- **Event template difilter per modul:** `MailSettingsService::emailEvents($module)` hanya menampilkan event milik modul terpilih ditambah event `approval.*` generik. Modul "Default (semua modul)" tetap menampilkan seluruh 16 event, sehingga template global masih bisa diatur. Contoh: `leave` → `leave.submitted` + 7 event approval; `it` → `it.submitted`, `it.completed` + 7 event approval.
- **Tampilan input template dirapikan:** tabel 6 kolom diganti menjadi kartu per event (grid 2 kolom untuk Subjek/Pesan Indonesia–English) dengan badge sumber `Isi asli`/`Default`/`Modul`; input tidak lagi sempit dan tetap rapi di layar kecil.
- Perbaikan badge sumber template yang sebelumnya memakai ekspresi di dalam string `className` (tidak dievaluasi).
- **Template email terisi otomatis:** seeder baru `EmailTemplateSeeder` mengisi 16 event dengan subjek/pesan Bahasa Indonesia + English sebagai template default (modul `NULL`, berlaku semua modul). Idempoten via `firstOrCreate`, sehingga editan admin tidak tertimpa saat dijalankan ulang. Terdaftar di `FoundationSeeder`, jadi `db:seed` ikut mengisinya.
- **Ikon sidebar diperjelas:** `Workflow` (diagram alur), `Modul & Maintenance` (slider), `Email & Notifikasi` (amplop), dan `ERP Request` (layer bertumpuk, menggantikan ikon matahari).
- **Placeholder template email:** subjek/pesan mendukung `{nomor_dokumen}`, `{modul}`, `{pelaku}`, `{tahap}`, `{judul}`, `{pesan}`, `{tautan}` (alias Inggris: `{document_number}`, `{module}`, `{actor}`, `{step}`, `{title}`, `{message}`, `{url}`). Diisi otomatis di `SendWorkflowEmail` memakai data approval sebenarnya; `{tahap}` diterjemahkan ke label (HOD, HRGA, Finance, dst.). Halaman template menampilkan daftar placeholder yang bisa diklik untuk menyisipkan ke kolom aktif. Template bawaan seeder memakai placeholder agar email memuat nomor dokumen, modul, pelaku, dan tahap.
- Perbaikan bug: `SendWorkflowEmail::$data` bersifat `readonly` sehingga override template melempar error saat template terisi; kini memakai salinan lokal.
- **Logo email tidak lagi bergantung pada URL publik:** logo di header email di-embed langsung ke pesan lewat `$message->embed(public_path('images/logo-bp.png'))` (CID/inline attachment), sehingga tetap tampil meski `APP_URL` masih `http://localhost` atau klien memblokir gambar eksternal. Aset baru `public/images/logo-bp.png` (160×160) dibuat dari `logo-bp.svg`, karena banyak klien email (Gmail/Outlook) tidak merender SVG di `<img>`.

## 2026-10-20

- **Email workflow (skema produksi):** setiap notifikasi database kini juga mengirim email melalui job `SendWorkflowEmail` (antrian `database`, `afterCommit`, retry 3×) dan mailable `WorkflowMail` — satu pintu di `InAppNotifier`, logika penerima notifikasi tidak berubah. Template `mail/workflow` (Bahasa Indonesia, header band perusahaan, tombol "Buka di eForm BP") memakai payload yang sama dengan notifikasi in-app. Guard: `users.email_notifications` (default true, migration `2026_10_20_000002`), kill-switch `MAIL_NOTIFICATIONS_ENABLED`, blacklist `eform.mail.skip_events` (`approval.step_deferred`, `approval.chain_step_cancelled`), email kosong/dummy `.invalid` di-skip. `.env` diarahkan skema SMTP produksi (`MAIL_FROM_ADDRESS=admin.it@borneoprima.com`) dengan kredensial SMTP yang perlu diisi sebelum deploy.

- Tombol "Cetak untuk Commercial" diganti menjadi **Save to PDF** dan langsung membuka dokumen formulir lengkap; modal pilih format dihapus.
- Opsi **Memo Penerusan** dan **Daftar Item** dihapus dari halaman dokumen. Halaman `it-requests.print` kini hanya merender formulir lengkap beserta matriks tanda tangan; parameter `format` lama diabaikan agar tautan lama tetap aman.
- Halaman `it-requests.print` kini merender **ringkasan satu halaman A4** berjudul "IT & Telecommunication Acquisition Request": kop perusahaan, data penerima, ringkasan permintaan/perangkat/software, deskripsi & tujuan yang dipersingkat, dan baris tanda tangan (satu kotak per tahap, tanpa duplikat).
- Akses Save to PDF **khusus IT** (`it_request.review`, status completed) melalui ability `print` pada `ItRequestPolicy`; tombol Save to PDF hanya muncul untuk IT (`availableActions.can_print`).
- **Redesign dokumen mengikuti mock-up varian V5 "Korporat Aksen Biru"** (`docs/mockups/it-print-mockup.html`): header band gelap dengan nomor request monospace, judul biru beraksen garis, chip info berlatar abu, tabel perangkat ber-header indigo, kartu tanda tangan dengan **cap stempel miring "✓ APPROVED VIA EFORM"** (border ganda, kemiringan ±9°, gaya tinta stempel), dan footer catatan indigo — semua memakai font **Plus Jakarta Sans** (warisan global aplikasi). Chip **"Dibutuhkan"** dan **"Prioritas"** dihapus dari dokumen karena field ini tidak tersedia di form input pemohon (nilainya tidak pernah terisi dari UI).
- Label tahap **"IT Service Desk"** di dokumen diganti menjadi **"IT"**; kolom **"Diterima Commercial"** dihapus dari baris tanda tangan; **jenis permintaan** (Barang baru/Penggantian) dipindah ke card Penerima di bawah nama.
- **Nomor PR MSD:** kolom baru `it_requests.pr_msd_number` (migration `2026_10_20_000001_add_pr_msd_number_to_it_requests`). Setelah request selesai, pemegang `it_request.review` (IT/admin) melihat kartu pengingat "segera buat PR di MSD" di halaman detail dengan input nomor PR; pengisian opsional dan setiap perubahan masuk audit log `it.pr_msd_number_updated`. Nomor PR ditampilkan sebagai kolom "Nomor PR" di listing IT request dan ikut dicocokkan pada pencarian server-side, tetapi tidak ditampilkan di dokumen Save to PDF.
- **Listing IT request:** baris berstatus completed menampilkan tombol **Save to PDF** hanya untuk pemegang `it_request.review` (`can_print` per baris).
- Tampilan halaman Save to PDF dan tombol aksi disesuaikan untuk dark mode (toolbar, tombol Kembali/Save to PDF, latar halaman). Kertas dokumen tetap putih agar hasil cetak konsisten.

- **Sidebar workflow digabung menjadi 1 entri:** 6 entri workflow per modul (cuti, dinas, settlement, medical, IT, ERP) diganti satu entri "Workflow" → `/settings/workflows`.
- **Pengaturan email per modul:** tabel `mail_settings` diberi kolom `module` (nullable, unique) sehingga setiap modul (cuti, dinas, settlement, medical, IT, ERP) dapat punya konfigurasi SMTP sendiri; modul tanpa baris sendiri mengikuti baris default (`.env`). Halaman `Settings/Mail/Index` kini memilih modul dan menyimpan pengaturan per modul, serta menampilkan ringkasan sumber konfigurasi untuk semua modul.

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
