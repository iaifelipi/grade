<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_monitoring_incidents')) {
            return;
        }

        Schema::table('admin_monitoring_incidents', function (Blueprint $table): void {
            if (!Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->index()->after('created_at');
            }
            if (!Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_by_user_id')) {
                $table->unsignedBigInteger('acknowledged_by_user_id')->nullable()->index()->after('acknowledged_at');
            }
            if (!Schema::hasColumn('admin_monitoring_incidents', 'ack_comment')) {
                $table->string('ack_comment', 255)->nullable()->after('acknowledged_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_monitoring_incidents')) {
            return;
        }

        Schema::table('admin_monitoring_incidents', function (Blueprint $table): void {
            if (Schema::hasColumn('admin_monitoring_incidents', 'ack_comment')) {
                $table->dropColumn('ack_comment');
            }
            if (Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_by_user_id')) {
                $table->dropColumn('acknowledged_by_user_id');
            }
            if (Schema::hasColumn('admin_monitoring_incidents', 'acknowledged_at')) {
                $table->dropColumn('acknowledged_at');
            }
        });
    }
};

