<?php

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->tenantIdFromContext();
        $isGlobalSuper = Auth::check()
            && method_exists(Auth::user(), 'isSuperAdmin')
            && Auth::user()->isSuperAdmin()
            && !session()->has('impersonate_user_id');

        return [
            'tenant_uuid' => [$isGlobalSuper ? 'required' : 'nullable', 'string', 'exists:tenants,uuid'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:190',
                Rule::unique('tenant_users', 'email')->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'username' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/',
                Rule::unique('tenant_users', 'username')->where(fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'group_id' => ['nullable', 'integer', 'exists:tenant_user_groups,id'],
            'price_plan_id' => ['nullable', 'integer', 'exists:monetization_price_plans,id'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', Rule::in(['active', 'invited', 'disabled'])],
            'is_primary_account' => ['nullable', 'boolean'],
            'send_welcome_email' => ['nullable', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
            'deactivate_at' => ['nullable', 'date'],
            'avatar_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function tenantIdFromContext(): int
    {
        $isGlobalSuper = Auth::check()
            && method_exists(Auth::user(), 'isSuperAdmin')
            && Auth::user()->isSuperAdmin()
            && !session()->has('impersonate_user_id');

        $tenantUuid = $isGlobalSuper
            ? trim((string) $this->input('tenant_uuid', ''))
            : (app()->bound('tenant_uuid') ? (string) app('tenant_uuid') : '');

        if ($tenantUuid === '') {
            return 0;
        }

        return (int) Tenant::query()->where('uuid', $tenantUuid)->value('id');
    }
}
