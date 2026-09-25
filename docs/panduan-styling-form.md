# Panduan styling form eForm BP

Gunakan tampilan form **Cuti/Izin** (`resources/js/Pages/Leaves/LeaveForm.jsx`) dan **Perjalanan Dinas** (`resources/js/Pages/Travels/TravelForm.jsx`) sebagai acuan visual untuk form transaksi lain, termasuk Settlement dan Medical Claim. Aturan CSS rujukan berada di `resources/css/app.css` (`worksheet-*`, `leave-sheet*`, dan `travel-sheet*`). Ini adalah panduan untuk pengembangan berikutnya; keberadaannya tidak berarti seluruh form yang sudah ada telah diselaraskan.

## Struktur dan jarak

- Pertahankan layout aplikasi dengan lebar form maksimum **1100px**, terpusat, dan padding form **1.25rem**. Gunakan jarak antarseksi **1rem** (`space-y-4`).
- Header identitas memakai tiga area (logo, judul, nomor formulir), gradien `#0066FF` ke `#0F172A`, border `#BFDBFE`, radius **0.9rem**. Jangan menyalin nomor formulir dari modul lain.
- Kelompokkan input ke dalam section/card putih dengan border `#E2E8F0`, radius **0.75rem**, padding **1.25rem**, dan bayangan halus. Heading section memiliki pembatas bawah `#DBEAFE` dan jarak bawah **1rem**.
- Gunakan eyebrow section berwarna `#0066FF` dan judul yang ringkas. Pertahankan urutan dan nama section sesuai kebutuhan masing-masing modul, bukan menyalin field atau kategori dari form contoh.
- Sub-section biaya menggunakan gap **0.9rem**, border `#CBD5E1`, radius **0.65rem**, header biru muda `#EFF6FF` dengan border bawah `#BFDBFE`. Item di dalamnya diberi margin **0.65rem** dari tepi kartu, bukan menempel langsung pada header/sub-section.
- Baris item memiliki border `#CBD5E1`, radius **0.65rem**, header abu muda `#F1F5F9`, dan sel putih dengan pemisah `#E2E8F0` serta padding **0.55rem**. Nomor baris memakai `.worksheet-row-index`.

## Field, badge, dan tombol

- Input yang dapat diedit: latar `#EAF5FF`, border `#BFDBFE`, radius sekitar **0.5–0.55rem**. Field read-only: latar **putih**. Label kecil, jelas, warna slate; tanda wajib merah. Tampilkan `InputError` di dekat field dan ringkasan error bila form panjang.
- Bila karyawan bisa dipilih (misalnya saat membuat transaksi atas nama), gunakan lookup bersama `resources/js/Components/EmployeeLookup.jsx` seperti di form Cuti dan Perjalanan Dinas: pencarian dengan NIK atau nama, opsi menampilkan NIK, nama, dan departemen; nilai form tetap `employee_id` dari daftar karyawan yang diberikan server. Karyawan tunggal tampil sebagai profil master read-only. Jangan pakai input NIK bebas atau mengandalkan penyaringan UI sebagai authorization.
- Badge estimasi total section berbentuk pil (`rounded-full`) dengan `bg-emerald-50`, `text-emerald-700`, padding `px-3 py-1`, teks `text-xs font-semibold`. Labelkan **Estimasi** karena total akhir dihitung server. Badge hari memakai `bg-indigo-50`/`text-indigo-700` bila perlu. Estimasi per item berupa teks hijau `#047857`, bukan badge besar yang berbeda warna per modul.
- Tombol tambah baris: latar putih, border `#93C5FD`, radius **0.45rem**, teks biru `#1D4ED8`. Tombol hapus kecil berwarna merah; aksi simpan memakai tombol utama biru dan batal tombol berbingkai putih seperti form Cuti.
- Info total lanjutan atau reconciliation boleh memakai kartu ringkasan, tetapi warna, jarak, dan badge-nya tetap konsisten. Hindari summary tambahan yang menduplikasi badge section tanpa informasi baru.

## Perilaku dan penerapan

- Desktop-first, tetapi grid tidak boleh membuat halaman meluap pada layar kecil; gunakan grid responsif atau area gulir yang jelas untuk tabel yang memang perlu lebar minimum.
- Gunakan class dasar `.worksheet-form`, `.worksheet-section-heading`, `.worksheet-eyebrow`, `.worksheet-row`, `.worksheet-fields`, `.worksheet-cell` dan class khusus modul hanya untuk perbedaan struktur. Periksa specificity/cascade CSS agar aturan modul tidak diam-diam menimpa warna input atau badge acuan.
- Saat menyelaraskan Settlement atau Medical Claim, pertahankan field, validasi, permission, dan kalkulasi aslinya; visual tidak mengubah kontrak Inertia atau keputusan backend.
- Cek form create dan edit, row kosong dan terisi, pesan error, serta tampilan tablet/mobile. Jalankan `npm run build` dan tes modul terkait setelah perubahan.
