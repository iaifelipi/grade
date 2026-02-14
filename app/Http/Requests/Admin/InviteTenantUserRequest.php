<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class InviteTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isGlobalSuper = Auth::check()
            && method_exists(Auth::user(), 'isSuperAdmin')
            && Auth::user()->isSuperAdmin()
            && !session()->has('impersonate_user_id');

        return [
            'tenant_uuid' => [$isGlobalSuper ? 'required' : 'nullable', 'string', 'exists:tenants,uuid'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'group_id' => ['nullable', 'integer', 'exists:tenant_user_groups,id'],
            'send_email' => ['nullable', 'boolean'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ];
    }
}
