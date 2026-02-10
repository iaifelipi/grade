<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenants', 'slug')) {
            return;
        }

        $tenants = DB::table('tenants')->select(['id', 'name', 'slug'])->get();
        foreach ($tenants as $tenant) {
            if (!empty($tenant->slug)) {
                continue;
            }

            $base = Str::slug((string) $tenant->name);
            $slug = $base ?: Str::random(8);

            $exists = DB::table('tenants')->where('slug', $slug)->exists();
            if ($exists) {
                $slug = $slug . '-' . Str::random(4);
            }

            DB::table('tenants')->where('id', $tenant->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        // sem rollback
    }
};
