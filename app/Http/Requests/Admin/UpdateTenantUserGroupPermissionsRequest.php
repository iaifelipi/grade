<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantUserGroupPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_uuid' => ['nullable', 'string', 'exists:tenants,uuid'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::in([
                    '*',
                    'imports.manage',
                    'data.edit',
                    'campaigns.manage',
                    'campaigns.run',
                    'inbox.manage',
                    'inbox.view',
                    'exports.manage',
                    'exports.run',
                    'exports.view',
                ]),
            ],
        ];
    }
}
