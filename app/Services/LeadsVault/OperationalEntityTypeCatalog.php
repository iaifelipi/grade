<?php

namespace App\Services\LeadsVault;

use App\Models\OperationalEntityType;
use App\Support\TenantStorage;
use Illuminate\Support\Str;

class OperationalEntityTypeCatalog
{
    /**
     * @return array<int,array{key:string,label:string}>
     */
    public function list(?string $tenantUuid = null): array
    {
        $tenant = $tenantUuid ?: TenantStorage::tenantUuidOrNull();

        $query = OperationalEntityType::query()
            ->where('is_active', true)
            ->orderByRaw('CASE WHEN tenant_uuid IS NULL THEN 1 ELSE 0 END')
            ->orderBy('sort_order')
            ->orderBy('label');

        if ($tenant) {
            $query->where(function ($sub) use ($tenant): void {
                $sub->whereNull('tenant_uuid')
                    ->orWhere('tenant_uuid', $tenant);
            });
        } else {
            $query->whereNull('tenant_uuid');
        }

        $rows = $query->get(['tenant_uuid', 'key', 'label']);

        $unique = [];
        foreach ($rows as $row) {
            $key = Str::lower(trim((string) $row->key));
            if ($key === '' || isset($unique[$key])) {
                continue;
            }

            $unique[$key] = [
                'key' => $key,
                'label' => trim((string) $row->label) !== ''
                    ? trim((string) $row->label)
                    : Str::headline(str_replace('_', ' ', $key)),
            ];
        }

        return array_values($unique);
    }

    /**
     * @return array<int,string>
     */
    public function allowedKeys(?string $tenantUuid = null): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['key'],
            $this->list($tenantUuid)
        );
    }

    public function isAllowed(?string $value, ?string $tenantUuid = null): bool
    {
        $key = Str::lower(trim((string) $value));
        if ($key === '') {
            return false;
        }

        return in_array($key, $this->allowedKeys($tenantUuid), true);
    }
}

