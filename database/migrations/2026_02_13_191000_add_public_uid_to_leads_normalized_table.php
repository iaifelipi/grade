<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leads_normalized')) {
            return;
        }

        if (!Schema::hasColumn('leads_normalized', 'public_uid')) {
            Schema::table('leads_normalized', function (Blueprint $table): void {
                $table->string('public_uid', 14)->nullable();
            });
        }

        DB::table('leads_normalized')
            ->select(['id', 'public_uid'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    if (!empty($row->public_uid)) {
                        continue;
                    }

                    DB::table('leads_normalized')
                        ->where('id', (int) $row->id)
                        ->update(['public_uid' => $this->generatePublicUid()]);
                }
            });

        Schema::table('leads_normalized', function (Blueprint $table): void {
            $table->unique('public_uid', 'leads_normalized_public_uid_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads_normalized')) {
            return;
        }

        if (Schema::hasColumn('leads_normalized', 'public_uid')) {
            Schema::table('leads_normalized', function (Blueprint $table): void {
                $table->dropUnique('leads_normalized_public_uid_unique');
                $table->dropColumn('public_uid');
            });
        }
    }

    private function generatePublicUid(): string
    {
        do {
            $uid = 'm' . strtolower(Str::random(13));
        } while (DB::table('leads_normalized')->where('public_uid', $uid)->exists());

        return $uid;
    }
};

