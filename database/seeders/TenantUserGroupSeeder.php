<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantUserGroupSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasTable('tenant_user_groups')) {
            return;
        }

        $defaults = [
            [
                'slug' => 'owner',
                'name' => 'Owner',
                'description' => 'Full tenant access.',
                'is_default' => true,
                'permissions_json' => ['*'],
            ],
            [
                'slug' => 'manager',
                'name' => 'Manager',
                'description' => 'Manage operations, campaigns and inbox.',
                'permissions_json' => ['imports.manage', 'data.edit', 'campaigns.manage', 'campaigns.run', 'inbox.manage', 'inbox.view', 'exports.manage', 'exports.run', 'exports.view'],
            ],
            [
                'slug' => 'operator',
                'name' => 'Operator',
                'description' => 'Operate imports, edits and dispatches.',
                'permissions_json' => ['imports.manage', 'data.edit', 'campaigns.run', 'inbox.view', 'exports.run', 'exports.view'],
            ],
            [
                'slug' => 'viewer',
                'name' => 'Viewer',
                'description' => 'Read-only operational access.',
                'permissions_json' => ['inbox.view', 'exports.view'],
            ],
        ];

        $now = now();

        Tenant::query()
            ->select(['id', 'uuid'])
            ->chunkById(200, function ($tenants) use ($defaults, $now) {
                foreach ($tenants as $tenant) {
                    foreach ($defaults as $group) {
                        DB::table('tenant_user_groups')->updateOrInsert(
                            [
                                'tenant_id' => $tenant->id,
                                'slug' => $group['slug'],
                            ],
                            [
                                'tenant_uuid' => $tenant->uuid,
                                'name' => $group['name'],
                                'description' => $group['description'],
                                'is_default' => (bool) ($group['is_default'] ?? false),
                                'is_active' => true,
                                'permissions_json' => json_encode($group['permissions_json'] ?? [], JSON_UNESCAPED_UNICODE),
                                'updated_at' => $now,
                                'created_at' => $now,
                            ]
                        );
                    }
                }
            });
    }
}
