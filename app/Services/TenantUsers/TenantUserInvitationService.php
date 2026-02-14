<?php

namespace App\Services\TenantUsers;

use App\Jobs\SendTenantUserInvitationEmailJob;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\TenantUserGroup;
use App\Models\TenantUserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantUserInvitationService
{
    public function create(
        Tenant $tenant,
        string $email,
        ?string $firstName,
        ?string $lastName,
        ?int $groupId,
        ?int $inviterUserId,
        ?int $inviterTenantUserId,
        bool $sendEmail = true,
        int $expiresInHours = 48
    ): array {
        $email = trim(mb_strtolower($email));
        $firstName = $firstName !== null ? trim($firstName) : null;
        $lastName = $lastName !== null ? trim($lastName) : null;

        return DB::transaction(function () use ($tenant, $email, $firstName, $lastName, $groupId, $inviterUserId, $inviterTenantUserId, $sendEmail, $expiresInHours) {
            $token = Str::random(64);
            $tokenHash = hash('sha256', $token);

            $group = null;
            if ($groupId) {
                $group = TenantUserGroup::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('id', $groupId)
                    ->first();
            }
            if (!$group) {
                $group = $this->resolveDefaultGroup($tenant);
            }

            $invitation = new TenantUserInvitation();
            $invitation->token_hash = $tokenHash;
            $invitation->tenant_id = $tenant->id;
            $invitation->tenant_uuid = $tenant->uuid;
            $invitation->email = $email;
            $invitation->first_name = $firstName;
            $invitation->last_name = $lastName;
            $invitation->name = trim((string) ($firstName . ' ' . $lastName)) ?: null;
            $invitation->group_id = $group?->id;
            $invitation->inviter_user_id = $inviterUserId;
            $invitation->inviter_tenant_user_id = $inviterTenantUserId;
            $invitation->expires_at = now()->addHours(max(1, min(168, $expiresInHours)));
            $invitation->accepted_at = null;
            $invitation->revoked_at = null;
            $invitation->save();

            $existing = TenantUser::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->first();

            if (!$existing) {
                TenantUser::query()->create([
                    'tenant_id' => $tenant->id,
                    'tenant_uuid' => $tenant->uuid,
                    'group_id' => $group?->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'name' => trim((string) ($firstName . ' ' . $lastName)) ?: $email,
                    'email' => $email,
                    'password' => Hash::make(Str::random(48)),
                    'status' => 'invited',
                    'send_welcome_email' => $sendEmail,
                    'invited_at' => now(),
                    'deactivate_at' => now(),
                ]);
            }

            $url = route('tenant.invite.accept', ['token' => $token]);

            if ($sendEmail) {
                SendTenantUserInvitationEmailJob::dispatch($invitation->id, $token)->onQueue('extras');
            }

            return ['invitation' => $invitation, 'token' => $token, 'url' => $url];
        });
    }

    public function findByToken(string $token): ?TenantUserInvitation
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return TenantUserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    public function accept(TenantUserInvitation $invitation, array $input): TenantUser
    {
        if ($invitation->isAccepted()) {
            abort(410, 'Invite already used.');
        }
        if ($invitation->isExpired()) {
            abort(410, 'Invite expired.');
        }

        return DB::transaction(function () use ($invitation, $input) {
            $email = trim(mb_strtolower((string) $invitation->email));

            $tenantUser = TenantUser::query()
                ->where('tenant_id', $invitation->tenant_id)
                ->where('email', $email)
                ->first();

            if (!$tenantUser) {
                $tenantUser = new TenantUser();
                $tenantUser->tenant_id = $invitation->tenant_id;
                $tenantUser->tenant_uuid = $invitation->tenant_uuid;
                $tenantUser->email = $email;
            }

            $firstName = trim((string) ($input['first_name'] ?? ''));
            $lastName = trim((string) ($input['last_name'] ?? ''));

            $tenantUser->first_name = $firstName !== '' ? $firstName : ($invitation->first_name ?: null);
            $tenantUser->last_name = $lastName !== '' ? $lastName : ($invitation->last_name ?: null);
            $tenantUser->name = trim(($tenantUser->first_name ?? '') . ' ' . ($tenantUser->last_name ?? '')) ?: ($invitation->name ?: $email);
            $tenantUser->password = Hash::make((string) $input['password']);
            $tenantUser->group_id = $invitation->group_id ?: $tenantUser->group_id;
            $tenantUser->email_verified_at = $tenantUser->email_verified_at ?: now();
            $tenantUser->status = 'active';
            $tenantUser->accepted_at = now();
            $tenantUser->deactivate_at = null;
            $tenantUser->must_change_password = false;
            $tenantUser->save();

            $invitation->accepted_at = now();
            $invitation->save();

            return $tenantUser;
        });
    }

    private function resolveDefaultGroup(Tenant $tenant): ?TenantUserGroup
    {
        $group = TenantUserGroup::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if ($group) {
            return $group;
        }

        return TenantUserGroup::query()->create([
            'tenant_id' => $tenant->id,
            'tenant_uuid' => $tenant->uuid,
            'name' => 'Operator',
            'slug' => 'operator',
            'description' => 'Default tenant operator.',
            'is_default' => true,
            'is_active' => true,
            'permissions_json' => ['imports.manage', 'data.edit', 'campaigns.run', 'inbox.view', 'exports.run'],
        ]);
    }
}
