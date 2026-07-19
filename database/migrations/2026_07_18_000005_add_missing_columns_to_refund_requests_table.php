<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('refund_requests', 'evidence_video_path')) {
                $table->string('evidence_video_path')->nullable()->after('evidence_path');
            }
            if (! Schema::hasColumn('refund_requests', 'inspection_notes')) {
                $table->text('inspection_notes')->nullable()->after('admin_note');
            }
            if (! Schema::hasColumn('refund_requests', 'inspected_by')) {
                $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete()->after('reviewed_by');
            }
            if (! Schema::hasColumn('refund_requests', 'inspected_at')) {
                $table->timestamp('inspected_at')->nullable()->after('inspected_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropColumn(['evidence_video_path', 'inspection_notes', 'inspected_by', 'inspected_at']);
        });
    }
};
