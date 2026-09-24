# Medical Claim Privacy

Payment policy MVP memakai permission `medical.payment.process` dan `medical.payment.complete`, tanpa bergantung pada nama role. Aktor harus aktif dan memiliki employee aktif; proses dan penyelesaian dipisah untuk maker-checker.
Finance hanya menerima aggregate `claim_number`, `status`, `total_amount`, dan label manfaat generik; Finance tidak mendapat patient, facility, diagnosis, activity properties, atau attachment. HRGA dan HRGA Manager melakukan review detail dengan `medical.review` dan `medical.view.sensitive`. Employee hanya dapat melihat claim miliknya, sedangkan Auditor mendapat aggregate anonim melalui `medical.view.aggregate`.

Spouse dan child harus berasal dari `medical_dependents` aktif milik employee. Nama pasien self selalu dibuat dari employee snapshot server-side. Attachment medical disimpan pada disk private dan download memerlukan policy owner atau reviewer sensitif; Finance dan Auditor selalu ditolak. Completion wajib menyimpan reference dan tanggal pembayaran serta dicatat bersama amount, actor, dan waktu.
