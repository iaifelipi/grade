<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guest_sessions')) {
            Schema::table('guest_sessions', function (Blueprint $table) {
                if (!Schema::hasColumn('guest_sessions', 'actor_type')) {
                    $table->string('actor_type', 20)->default('guest')->index()->after('guest_uuid');
                }
                if (!Schema::hasColumn('guest_sessions', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->index()->after('actor_type');
                }
                if (!Schema::hasColumn('guest_sessions', 'ip_raw')) {
                    $table->string('ip_raw', 45)->nullable()->after('session_id');
                }
                if (!Schema::hasColumn('guest_sessions', 'ip_enc')) {
                    $table->text('ip_enc')->nullable()->after('ip_raw');
                }
                if (!Schema::hasColumn('guest_sessions', 'ua_raw')) {
                    $table->text('ua_raw')->nullable()->after('ua_hash');
                }
                if (!Schema::hasColumn('guest_sessions', 'os_family')) {
                    $table->string('os_family', 40)->nullable()->after('ua_raw');
                }
                if (!Schema::hasColumn('guest_sessions', 'os_version')) {
                    $table->string('os_version', 40)->nullable()->after('os_family');
                }
                if (!Schema::hasColumn('guest_sessions', 'browser_family')) {
                    $table->string('browser_family', 40)->nullable()->after('os_version');
                }
                if (!Schema::hasColumn('guest_sessions', 'browser_version')) {
                    $table->string('browser_version', 40)->nullable()->after('browser_family');
                }
                if (!Schema::hasColumn('guest_sessions', 'device_type')) {
                    $table->string('device_type', 20)->nullable()->after('browser_version');
                }
                if (!Schema::hasColumn('guest_sessions', 'hardware_raw')) {
                    $table->string('hardware_raw', 120)->nullable()->after('device_type');
                }
            });
        }

        if (Schema::hasTable('guest_file_events')) {
            Schema::table('guest_file_events', function (Blueprint $table) {
                if (!Schema::hasColumn('guest_file_events', 'actor_type')) {
                    $table->string('actor_type', 20)->default('guest')->index()->after('guest_uuid');
                }
                if (!Schema::hasColumn('guest_file_events', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->index()->after('actor_type');
                }
                if (!Schema::hasColumn('guest_file_events', 'ip_raw')) {
                    $table->string('ip_raw', 45)->nullable()->after('session_id');
                }
                if (!Schema::hasColumn('guest_file_events', 'ip_enc')) {
                    $table->text('ip_enc')->nullable()->after('ip_raw');
                }
                if (!Schema::hasColumn('guest_file_events', 'ua_raw')) {
                    $table->text('ua_raw')->nullable()->after('file_size_bytes');
                }
                if (!Schema::hasColumn('guest_file_events', 'os_family')) {
                    $table->string('os_family', 40)->nullable()->after('ua_raw');
                }
                if (!Schema::hasColumn('guest_file_events', 'os_version')) {
                    $table->string('os_version', 40)->nullable()->after('os_family');
                }
                if (!Schema::hasColumn('guest_file_events', 'browser_family')) {
                    $table->string('browser_family', 40)->nullable()->after('os_version');
                }
                if (!Schema::hasColumn('guest_file_events', 'browser_version')) {
                    $table->string('browser_version', 40)->nullable()->after('browser_family');
                }
                if (!Schema::hasColumn('guest_file_events', 'device_type')) {
                    $table->string('device_type', 20)->nullable()->after('browser_version');
                }
                if (!Schema::hasColumn('guest_file_events', 'hardware_raw')) {
                    $table->string('hardware_raw', 120)->nullable()->after('device_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('guest_file_events')) {
            Schema::table('guest_file_events', function (Blueprint $table) {
                foreach ([
                    'hardware_raw',
                    'device_type',
                    'browser_version',
                    'browser_family',
                    'os_version',
                    'os_family',
                    'ua_raw',
                    'ip_enc',
                    'ip_raw',
                    'user_id',
                    'actor_type',
                ] as $column) {
                    if (Schema::hasColumn('guest_file_events', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('guest_sessions')) {
            Schema::table('guest_sessions', function (Blueprint $table) {
                foreach ([
                    'hardware_raw',
                    'device_type',
                    'browser_version',
                    'browser_family',
                    'os_version',
                    'os_family',
                    'ua_raw',
                    'ip_enc',
                    'ip_raw',
                    'user_id',
                    'actor_type',
                ] as $column) {
                    if (Schema::hasColumn('guest_sessions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

