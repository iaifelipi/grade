<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

class PixipPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // role templates são aplicados após criar permissões
        /*
        |--------------------------------------------------------------------------
        | PERMISSÕES PIXIP (organizadas por módulo)
        |--------------------------------------------------------------------------
        */

        $permissions = [

            // Leads / Sources
            'leads.import',
            'leads.view',
            'leads.export',
            'leads.delete',

            // Tratamento
            'leads.normalize',
            'leads.merge',
            'leads.clean',

            // Explore / Analytics
            'analytics.view',
            'analytics.export',

            // Automation / Jobs
            'automation.run',
            'automation.cancel',
            'automation.reprocess',

            // Admin
            'users.manage',
            'roles.manage',
            'system.settings',
            'audit.view_sensitive',
        ];


        /*
        |--------------------------------------------------------------------------
        | Criar permissões
        |--------------------------------------------------------------------------
        */
        foreach ($permissions as $perm) {
            Permission::firstOrCreate([
                'name' => $perm,
                'guard_name' => 'web',
            ]);
        }

        $this->ensureRoleTemplates();


        /*
        |--------------------------------------------------------------------------
        | Vincular primeiro usuário como admin automaticamente
        |--------------------------------------------------------------------------
        */

        $firstUser = User::first();
        if ($firstUser && !$firstUser->hasRole('admin', $firstUser->tenant_uuid)) {
            $adminRole = Role::query()
                ->where('tenant_uuid', $firstUser->tenant_uuid)
                ->where('name', 'admin')
                ->first();

            if ($adminRole) {
                $firstUser->roles()->syncWithoutDetaching([$adminRole->id]);
            }
        }
    }

    private function ensureRoleTemplates(): void
    {
        /*
        |--------------------------------------------------------------------------
        | TEMPLATE por tenant (roles padrão)
        |--------------------------------------------------------------------------
        */
        $tenants = User::query()
            ->whereNotNull('tenant_uuid')
            ->distinct()
            ->pluck('tenant_uuid');

        if ($tenants->isEmpty()) {
            $tenants = collect([null]);
        }

        foreach ($tenants as $tenantUuid) {
            $admin = Role::firstOrCreate([
                'name' => 'admin',
                'guard_name' => 'web',
                'tenant_uuid' => $tenantUuid,
            ]);
            $admin->syncPermissionsByName(Permission::query()->pluck('name')->all());

            $operator = Role::firstOrCreate([
                'name' => 'operator',
                'guard_name' => 'web',
                'tenant_uuid' => $tenantUuid,
            ]);
            $operator->syncPermissionsByName([
                'leads.import',
                'leads.view',
                'leads.export',
                'leads.normalize',
                'leads.merge',
                'automation.run',
                'automation.reprocess',
                'analytics.view',
            ]);

            $viewer = Role::firstOrCreate([
                'name' => 'viewer',
                'guard_name' => 'web',
                'tenant_uuid' => $tenantUuid,
            ]);
            $viewer->syncPermissionsByName([
                'leads.view',
                'analytics.view',
            ]);
        }
    }
}
