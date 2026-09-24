# eForm BP
## Product Requirements Document v1.1

### 1. Ringkasan

eForm BP adalah aplikasi internal untuk digitalisasi proses cuti/izin, perjalanan dinas, settlement biaya, dan medical claim PT Borneo Prima.

Target utama aplikasi:

- Menghilangkan pengisian formulir Excel manual.
- Mengisi data karyawan secara otomatis dari master karyawan.
- Menghitung advance, biaya aktual, dan selisih secara otomatis.
- Menyediakan approval bertingkat dan audit trail.
- Menyediakan penyimpanan bukti dan dokumen digital.

### 2. Scope MVP

#### Modul A: Cuti / Izin

Pengguna mengajukan cuti atau izin dengan rincian onsite, hari terakhir kerja, perjalanan ke rumah/lokasi, cuti roster/OS, C-Off, cuti tahunan, izin/lainnya, perjalanan ke site, onsite setelah cuti, dan alasan/catatan.

Sistem menghitung jumlah hari untuk setiap periode dan total hari cuti. Untuk karyawan non-lokal, sistem dapat menghitung biaya perjalanan, penginapan, makan, dan biaya lainnya. Untuk karyawan lokal, biaya perjalanan dapat bernilai nol sesuai konfigurasi kebijakan.

#### Modul B: Perjalanan Dinas

Pengguna mengajukan perjalanan dinas dengan data:

- Keperluan perjalanan.
- Tanggal mulai dan selesai.
- Lokasi asal dan tujuan.
- Transport darat.
- Tiket pesawat.
- Penginapan.
- Makan.
- Biaya lainnya.
- Lampiran undangan atau dokumen pendukung.

Sistem menghitung total advance berdasarkan seluruh komponen biaya.

#### Modul C: Settlement Cuti / Perjalanan Dinas

Pengguna memasukkan realisasi biaya setelah cuti atau perjalanan dinas selesai:

- Tanggal transaksi.
- Item biaya.
- Transport.
- Hotel.
- Makan.
- Biaya lainnya.
- Nota atau kwitansi.

Settlement dapat memilih sumber advance dari pengajuan cuti atau perjalanan dinas. Sistem membandingkan total realisasi dengan advance.

#### Modul D: Medical Claim

Pengguna mengajukan klaim kesehatan untuk:

- Karyawan.
- Istri.
- Anak.

Data klaim:

- Jenis manfaat.
- Nama pasien.
- Hubungan dengan karyawan.
- Tanggal berobat.
- Fasilitas kesehatan.
- Nominal biaya.
- Bukti pembayaran.
- Resep atau surat dokter jika diperlukan.

### 3. Di luar scope MVP

- Integrasi langsung dengan D365 Finance & Operations.
- Pembayaran otomatis ke payroll.
- Mobile application native.
- Real-time WebSocket.
- OCR otomatis untuk membaca nota.

### 4. Role pengguna

| Role | Tanggung jawab |
|---|---|
| Employee | Membuat pengajuan, memperbaiki pengajuan yang dikembalikan, mengajukan settlement |
| Supervisor/SPT | Memeriksa pengajuan jika diwajibkan oleh matrix |
| HOD | Menyetujui kebutuhan perjalanan dinas |
| Project Manager | Menyetujui perjalanan yang membutuhkan persetujuan project |
| HRGA | Memeriksa administrasi, memproses advance, dan memeriksa medical claim |
| HRGA Manager/Deputy | Mengetahui atau menyetujui proses sesuai matrix |
| Finance | Memeriksa settlement dan memproses selisih pembayaran |
| System Administrator | Mengelola master, role, workflow, dan konfigurasi |
| Auditor/Viewer | Melihat data dan laporan sesuai hak akses |

Role harus dapat dikonfigurasi per pengguna. Jangan mengandalkan nama jabatan yang ditulis bebas sebagai dasar authorization.

### 5. Workflow

#### 5.1 Cuti / Izin

```text
Draft
  -> Submitted
  -> In Review
  -> Approved
  -> Processing HRGA
  -> Advance Paid
  -> Completed
```

Approval default mengikuti matrix perusahaan: Supervisor/SPT jika diwajibkan, HOD, Project Manager jika diperlukan, lalu HRGA memproses administrasi dan biaya. Pengajuan dapat dikembalikan untuk koreksi, ditolak dengan alasan, atau dibatalkan sesuai kebijakan.

#### 5.2 Perjalanan Dinas

```text
Draft
  -> Submitted
  -> In Review
  -> Approved
  -> Processing HRGA
  -> Advance Paid
  -> Settlement Required
  -> Completed
```

Kemungkinan cabang:

- In Review -> Returned
- In Review -> Rejected
- Draft/Submitted -> Cancelled sesuai kebijakan

Approval step ditentukan oleh workflow matrix. Contoh default:

1. Atasan atau Supervisor memeriksa.
2. HOD menyetujui.
3. Project Manager menyetujui jika perjalanan terkait project.
4. HRGA memproses advance.
5. Finance dapat dilibatkan jika kebijakan mengharuskan.

#### 5.3 Settlement Cuti / Perjalanan Dinas

```text
Settlement Required
  -> Draft Settlement
  -> Submitted
  -> HRGA Review
  -> Finance Review
  -> Approved
  -> Payment/Adjustment Processing
  -> Completed
```

Hasil selisih:

- `OVERPAYMENT`: advance lebih besar daripada realisasi, dikembalikan ke perusahaan.
- `UNDERPAYMENT`: realisasi lebih besar daripada advance, dibayar kepada karyawan.
- `BALANCED`: tidak ada selisih.

#### 5.4 Medical Claim

```text
Draft
  -> Submitted
  -> HRGA Review
  -> Document Validation
  -> Approved
  -> Payment Processing
  -> Completed
```

### 6. Aturan bisnis

1. NIK harus terhubung dengan satu data karyawan aktif.
2. Nama, departemen, level, jabatan, roster, dan status POH diambil dari master.
3. Data profil pada dokumen transaksi disimpan sebagai snapshot agar histori tidak berubah ketika master karyawan diperbarui.
4. Total biaya tidak boleh dimasukkan sebagai angka bebas jika dapat dihitung dari item biaya.
5. Pengajuan yang sudah disetujui tidak boleh diedit langsung.
6. Perubahan setelah approval harus melalui returned/revision flow.
7. Setiap approval, penolakan, pengembalian, dan perubahan status dicatat di audit trail.
8. Lampiran dibatasi berdasarkan tipe dan ukuran file yang dikonfigurasi.
9. Settlement hanya dapat dibuat untuk advance yang berstatus `ADVANCE_PAID` atau status lain yang diizinkan admin.
10. Satu advance dapat memiliki satu settlement utama, kecuali admin mengaktifkan partial settlement.
11. Medical claim harus menyimpan hubungan pasien dengan karyawan.
12. Akses nominal dan dokumen medis dibatasi berdasarkan role.
13. Settlement dapat bersumber dari pengajuan cuti atau pengajuan perjalanan dinas.
14. Pengajuan cuti yang memiliki biaya advance dapat masuk ke status `SETTLEMENT_REQUIRED` setelah proses biaya selesai.
15. Kategori cuti dan aturan biaya harus dapat dikonfigurasi admin.

### 7. Data utama

#### employees

- id
- employee_number
- name
- department
- level
- job_title
- roster
- employment_status
- poh_status
- poh_city
- poh_province
- supervisor_id
- hod_id
- active

#### travel_requests

- id
- request_number
- employee_id
- purpose
- start_date
- end_date
- origin
- status
- total_advance
- submitted_at
- approved_at
- created_by
- updated_by

#### travel_request_items

- id
- travel_request_id
- category: `land_transport`, `flight`, `hotel`, `meal`, `other`
- transaction_date
- origin
- destination
- description
- quantity
- unit_price
- amount
- metadata_json

#### leave_requests

- id
- request_number
- employee_id
- leave_type
- reason
- status
- total_days
- total_advance
- last_working_date
- onsite_date
- submitted_at
- approved_at
- created_by
- updated_by

#### leave_periods

- id
- leave_request_id
- category: `onsite`, `travel_home`, `roster_leave`, `coff`, `annual_leave`, `permission`, `travel_to_site`
- start_date
- end_date
- day_count
- notes

#### leave_cost_items

- id
- leave_request_id
- category: `land_transport`, `hotel`, `meal`, `other`
- description
- quantity
- unit_price
- amount
- eligible_by_policy

#### settlements

- id
- settlement_number
- source_type: `leave_request` or `travel_request`
- source_id
- travel_request_id
- leave_request_id
- employee_id
- status
- advance_amount
- actual_amount
- difference_amount
- difference_type
- submitted_at
- completed_at

#### settlement_items

- id
- settlement_id
- transaction_date
- description
- category
- amount

#### medical_claims

- id
- claim_number
- employee_id
- status
- benefit_type
- total_amount
- submitted_at
- completed_at

#### medical_claim_items

- id
- medical_claim_id
- patient_name
- relationship
- treatment_date
- facility_name
- amount

#### attachments

- id
- attachable_type
- attachable_id
- document_type
- original_name
- stored_path
- mime_type
- file_size
- uploaded_by

#### approval_requests

- id
- approvable_type
- approvable_id
- step_order
- step_code
- approver_role
- approver_user_id
- status
- due_at
- acted_at
- comments

#### approval_actions

- id
- approval_request_id
- actor_id
- action: `approve`, `reject`, `return`, `delegate`, `cancel`
- comments
- created_at

### 8. Teknologi

- Backend: Laravel 12.x, PHP 8.3.
- Frontend: React + Inertia.js + Vite.
- UI: Tailwind CSS + shadcn/ui.
- Database: MySQL 8 / MariaDB 10.6+.
- Authentication: Laravel starter kit/Fortify.
- Permission: Spatie Laravel Permission.
- Audit: Spatie Activitylog.
- Queue MVP: database queue.
- Cache/session: database atau file untuk shared hosting.
- Storage MVP: local private storage.
- PDF MVP: DomPDF.
- Email: SMTP.
- Deployment: shared hosting dengan PHP, cron, SSL, dan MySQL.

### 9. Halaman utama

- Login.
- Dashboard.
- Daftar pengajuan saya.
- Buat perjalanan dinas.
- Detail perjalanan dinas.
- Buat cuti/izin.
- Detail cuti/izin.
- Approval inbox.
- Detail approval.
- Buat settlement.
- Detail settlement.
- Buat medical claim.
- Detail medical claim.
- Master karyawan.
- Konfigurasi workflow.
- Laporan.

### 10. Acceptance criteria MVP

- User dapat login dan melihat menu sesuai role.
- User dapat membuat perjalanan dinas dengan item biaya dinamis.
- User dapat membuat pengajuan cuti/izin dengan beberapa periode tanggal.
- Sistem menghitung jumlah hari setiap periode cuti dan total hari pengajuan.
- Sistem menerapkan aturan biaya lokal/non-lokal pada pengajuan cuti.
- Total advance dihitung otomatis.
- Pengajuan berjalan melalui approval step yang benar.
- Approver dapat approve, reject, atau return dengan catatan.
- User dapat membuat settlement berdasarkan cuti atau perjalanan yang telah memiliki advance.
- Settlement dapat mengambil sumber dari pengajuan cuti maupun perjalanan dinas.
- Sistem menghitung overpayment, underpayment, atau balanced.
- User dapat mengunggah bukti biaya.
- User dapat membuat medical claim untuk diri sendiri, istri, dan anak.
- HRGA dapat memeriksa dokumen dan status klaim.
- Semua perubahan status memiliki audit trail.
- Aplikasi dapat berjalan di shared hosting tanpa Redis dan WebSocket.

### 11. Keamanan

- Semua halaman internal memerlukan login.
- Authorization harus diperiksa di server, bukan hanya menyembunyikan tombol di frontend.
- Lampiran disimpan private dan diakses melalui signed/authorized download.
- Nomor dokumen dan ID internal tidak boleh menjadi satu-satunya kontrol akses.
- File upload harus divalidasi MIME type, extension, ukuran, dan nama file.
- Akses medical claim menggunakan role-based permission.
- Audit log tidak boleh dapat diubah oleh employee biasa.
