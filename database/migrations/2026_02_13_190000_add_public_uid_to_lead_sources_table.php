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
        if (!Schema::hasTable('lead_sources')) {
            return;
        }

        if (!Schema::hasColumn('lead_sources', 'public_uid')) {
            Schema::table('lead_sources', function (Blueprint $table): void {
                $table->string('public_uid', 14)->nullable();
            });
        }

        DB::table('lead_sources')
            ->select(['id', 'public_uid'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if (!empty($row->public_uid)) {
                        continue;
                    }

                    DB::table('lead_sources')
                        ->where('id', (int) $row->id)
                        ->update(['public_uid' => $this->generatePublicUid()]);
                }
            });

        Schema::table('lead_sources', function (Blueprint $table): void {
            $table->unique('public_uid', 'lead_sources_public_uid_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lead_sources')) {
            return;
        }

        if (Schema::hasColumn('lead_sources', 'public_uid')) {
            Schema::table('lead_sources', function (Blueprint $table): void {
                $table->dropUnique('lead_sources_public_uid_unique');
                $table->dropColumn('public_uid');
            });
        }
    }

    private function generatePublicUid(): string
    {
        do {
            $uid = 'x' . strtolower(Str::random(13));
        } while (DB::table('lead_sources')->where('public_uid', $uid)->exists());

        return $uid;
    }
};

