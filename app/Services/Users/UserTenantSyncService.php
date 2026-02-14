<?php

namespace App\Services\Users;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserTenantSyncService
{
    public function syncFromUser(User $user): TenantUser
    {
        $tenantUuid = trim((string) ($user->tenant_uuid ?? ''));
        if ($tenantUuid === '') {
            throw ValidationException::withMessages([
                'tenant_uuid' => 'Usuário sem tenant_uuid não pode ser sincronizado com tenant_users.',
            ]);
        }

        $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();
        if (!$tenant) {
            throw ValidationException::withMessages([
                'tenant_uuid' => 'Tenant não encontrado para sincronizar tenant user.',
            ]);
        }

        $email = mb_strtolower(trim((string) ($user->email ?? '')));
        if ($email === '') {
            throw ValidationException::withMessages([
                'email' => 'Usuário sem e-mail não pode ser sincronizado com tenant_users.',
            ]);
        }

        $username = $this->normalizeUsername((string) ($user->username ?? ''));
        if ($username === '') {
            $username = $this->usernameFromEmail($email);
        }

        $tenantUser = TenantUser::withTrashed()
            ->where('source_user_id', (int) $user->id)
            ->first();

        // Legacy fallback during transition: match by tenant+email/username only if link does not exist yet.
        if (!$tenantUser) {
            $tenantUser = TenantUser::withTrashed()
                ->where('tenant_id', (int) $tenant->id)
                ->where('email', $email)
                ->first();
        }

        if (!$tenantUser && $username !== '') {
            $tenantUser = TenantUser::withTrashed()
                ->where('tenant_id', (int) $tenant->id)
                ->where('username', $username)
                ->first();
        }

        if (!$tenantUser) {
            $tenantUser = new TenantUser();
            $tenantUser->tenant_id = (int) $tenant->id;
            $tenantUser->tenant_uuid = (string) $tenant->uuid;
            $tenantUser->send_welcome_email = false;
            $tenantUser->is_owner = false;
            $tenantUser->is_primary_account = false;
        }

        $tenantUser->username = $this->resolveUniqueUsername((int) $tenant->id, $username, $tenantUser->id);

        [$firstName, $lastName] = $this->splitName((string) ($user->name ?? ''));
        $isDisabled = !empty($user->disabled_at);

        $tenantUser->tenant_id = (int) $tenant->id;
        $tenantUser->tenant_uuid = (string) $tenant->uuid;
        $tenantUser->source_user_id = (int) $user->id;
        $tenantUser->name = trim((string) $user->name) !== '' ? trim((string) $user->name) : $email;
        $tenantUser->first_name = $firstName;
        $tenantUser->last_name = $lastName;
        $tenantUser->email = $email;
        $tenantUser->password = (string) $user->password;
        $tenantUser->status = $isDisabled ? 'disabled' : 'active';
        $tenantUser->deactivate_at = $isDisabled ? ($user->disabled_at ?: now()) : null;

        if (!$isDisabled) {
            $tenantUser->accepted_at = $tenantUser->accepted_at ?: now();
            $tenantUser->email_verified_at = $user->email_verified_at ?: ($tenantUser->email_verified_at ?: now());
        }

        if ($tenantUser->trashed()) {
            $tenantUser->restore();
        }

        $tenantUser->save();

        return $tenantUser;
    }

    public function deleteSyncedTenantUser(User $user): void
    {
        $tenantUuid = trim((string) ($user->tenant_uuid ?? ''));
        if ($tenantUuid === '') {
            return;
        }

        $tenant = Tenant::query()->where('uuid', $tenantUuid)->first();
        if (!$tenant) {
            return;
        }

        $email = mb_strtolower(trim((string) ($user->email ?? '')));
        $username = $this->normalizeUsername((string) ($user->username ?? ''));

        $tenantUser = TenantUser::query()
            ->where('source_user_id', (int) $user->id)
            ->first();

        if (!$tenantUser) {
            $fallback = TenantUser::query()->where('tenant_id', (int) $tenant->id);
            if ($email !== '') {
                $fallback->where('email', $email);
            } elseif ($username !== '') {
                $fallback->where('username', $username);
            } else {
                return;
            }

            $tenantUser = $fallback->first();
        }

        if ($tenantUser) {
            if ((int) ($tenantUser->source_user_id ?? 0) !== (int) $user->id) {
                $tenantUser->source_user_id = (int) $user->id;
                $tenantUser->save();
            }
            $tenantUser->delete();
        }
    }

    private function resolveUniqueUsername(int $tenantId, string $baseUsername, ?int $exceptId = null): string
    {
        $base = $this->normalizeUsername($baseUsername);
        if ($base === '') {
            $base = 'tenantuser';
        }

        $candidate = $base;
        $suffix = 1;

        while ($this->usernameExists($tenantId, $candidate, $exceptId)) {
            $suffix++;
            $candidate = $base . $suffix;
        }

        return $candidate;
    }

    private function usernameExists(int $tenantId, string $username, ?int $exceptId = null): bool
    {
        return TenantUser::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('username', $username)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->exists();
    }

    private function normalizeUsername(string $username): string
    {
        $username = mb_strtolower(trim($username));
        $username = preg_replace('/[^a-z0-9._-]+/', '', $username) ?: '';

        return mb_substr($username, 0, 64);
    }

    private function usernameFromEmail(string $email): string
    {
        $local = mb_strtolower(trim((string) strstr($email, '@', true)));
        $local = preg_replace('/[^a-z0-9._-]+/', '', $local) ?: 'tenantuser';

        return mb_substr($local, 0, 64);
    }

    /**
     * @return array{0:string,1:?string}
     */
    private function splitName(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['', null];
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = (string) ($parts[0] ?? '');

        if (count($parts) <= 1) {
            return [$first, null];
        }

        $last = trim(implode(' ', array_slice($parts, 1)));
        return [$first, $last !== '' ? $last : null];
    }
}
