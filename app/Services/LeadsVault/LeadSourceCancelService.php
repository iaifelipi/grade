<?php

namespace App\Services\LeadsVault;

use App\Models\LeadSource;
use Illuminate\Support\Facades\DB;

class LeadSourceCancelService
{
    public function cancel(int $sourceId): LeadSource
    {
        return DB::transaction(function () use ($sourceId) {

            $source = LeadSource::lockForUpdate()->findOrFail($sourceId);

            if (in_array($source->status, ['done','failed','cancelled'])) {
                return $source;
            }

            $source->update([
                'cancel_requested' => true,
                'status'           => 'cancelled'
            ]);

            return $source;
        });
    }
}
