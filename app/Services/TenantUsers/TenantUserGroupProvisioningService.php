<?php

namespace App\Services\TenantUsers;

use App\Models\Tenant;
use App\Models\TenantUserGroup;
use Illuminate\Support\Collection;

class TenantUserGroupProvisioningService
{
    /**
     * @return Collection<string,TenantUserGroup>
     */
    public function provisionForPlan(Tenant $tenant, ?string $plan = null): Collection
    {
        $resolvedPlan = $this->normalizePlan((string) ($plan ?: $tenant->plan ?: 'free'));
        $blueprint = $this->groupsBlueprint($resolvedPlan);

        $groups = collect();

        foreach ($blueprint as $group) {
            $model = TenantUserGroup::query()->updateOrCreate(
                [
                    'tenant_id' => (int) $tenant->id,
                    'slug' => (string) $group['slug'],
                ],
                [
                    'tenant_uuid' => (string) $tenant->uuid,
                    'name' => (string) $group['name'],
                    'description' => (string) ($group['description'] ?? ''),
                    'is_default' => (bool) ($group['is_default'] ?? false),
                    'is_active' => true,
                    'permissions_json' => array_values(array_unique(array_map('strval', $group['permissions_json'] ?? []))),
                ]
            );

            $groups->put((string) $group['slug'], $model);
        }

        return $groups;
    }

    private function normalizePlan(string $plan): string
    {
        $available = array_map('strval', config('plans.available', ['free', 'starter', 'pro']));
        return in_array($plan, $available, true) ? $plan : 'free';
    }

    /**
     * @return array<int,array{
     *   slug:string,
     *   name:string,
     *   description:string,
     *   is_default?:bool,
     *   permissions_json:array<int,string>
     * }>
     */
    private function groupsBlueprint(string $plan): array
    {
        $viewer = ['inbox.view', 'exports.view'];
        $operator = ['imports.manage', 'inbox.view'];
        $manager = ['imports.manage', 'data.edit', 'inbox.view', 'exports.view'];

        if (in_array($plan, ['starter', 'pro'], true)) {
            $operator = array_merge($operator, ['data.edit', 'campaigns.run', 'exports.run', 'exports.view']);
            $manager = array_merge($manager, ['campaigns.run', 'exports.run', 'exports.view']);
        }

        if ($plan === 'pro') {
            $manager = array_merge($manager, ['campaigns.manage', 'inbox.manage', 'exports.manage']);
            $operator = array_merge($operator, ['exports.run']);
        }

        return [
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
                'description' => 'Manage operations and team workflows.',
                'permissions_json' => $manager,
            ],
            [
                'slug' => 'operator',
                'name' => 'Operator',
                'description' => 'Operate day-to-day tasks.',
                'permissions_json' => $operator,
            ],
            [
                'slug' => 'viewer',
                'name' => 'Viewer',
                'description' => 'Read-only operational access.',
                'permissions_json' => $viewer,
            ],
        ];
    }
}

