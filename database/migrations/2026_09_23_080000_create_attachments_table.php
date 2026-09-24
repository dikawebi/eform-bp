<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * M-01: tabel attachments generik minimal (morph).
     *
     * - attachable_type/id: polymorph ke leave_requests / travel_requests /
     *   settlements / medical_claims (dipakai penuh mulai Phase 5).
     * - Kolom minimal: document_type, original_name, stored_path, mime_type,
     *   file_size, uploaded_by.
     * - DEFER FORMAL: upload lampiran cuti/dinas (UI form, validasi MIME/
     *   ukuran, private/signed download, policy file) SENGAJA tidak
     *   diimplementasi di Phase 2 dan aktif di Phase 5 (Advance & Settlement).
     *   Migrasi ini hanya menyiapkan storage agar Phase 5 tinggal pakai
     *   tanpa mengubah skema transaksi Phase 2.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 255);
            $table->unsignedBigInteger('attachable_id');
            $table->string('document_type', 64)->nullable();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('sha256_hash', 64)->nullable();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
