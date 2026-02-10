<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        /*
        |-------------------------------------------------
        | GLOBAL SCOPE (AUTO FILTRO TENANT)
        |-------------------------------------------------
        */
        static::addGlobalScope('tenant', function (Builder $query) {

            $uuid = app()->bound('tenant_uuid')
                ? app('tenant_uuid')
                : null;

            if (!$uuid) {
                return;
            }

            $query->where(
                $query->getModel()->getTable() . '.tenant_uuid',
                $uuid
            );
        });


        /*
        |-------------------------------------------------
        | AUTO PREENCHER AO CRIAR
        |-------------------------------------------------
        */
        static::creating(function ($model) {

            if (
                empty($model->tenant_uuid) &&
                app()->bound('tenant_uuid')
            ) {
                $model->tenant_uuid = app('tenant_uuid');
            }
        });
    }
}

