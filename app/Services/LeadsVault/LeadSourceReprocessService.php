<?php

namespace App\Services\LeadsVault;

use App\Models\LeadSource;
use App\Jobs\ImportLeadSourceJob;

use Illuminate\Support\Facades\DB;

class LeadSourceReprocessService
{
    public function reprocess(int $sourceId): LeadSource
    {
        return DB::transaction(function () use ($sourceId) {

            $source = LeadSource::lockForUpdate()->findOrFail($sourceId);

            /* purge previous raw rows */
            DB::table('lead_raw')
                ->where('lead_source_id', $source->id)
                ->delete();

            /* purge previous normalized rows */
            DB::table('leads_normalized')
                ->where('lead_source_id', $source->id)
                ->delete();

            /* reset state */
            $source->update([
                'status'            => 'queued',
                'progress_percent'  => 0,
                'processed_rows'    => 0,
                'inserted_rows'     => 0,
                'skipped_rows'      => 0,
                'last_error'        => null,
                'cancel_requested'  => false,
                'started_at'        => null,
                'finished_at'       => null,
            ]);

            /* dispatch again */
            ImportLeadSourceJob::dispatch($source->id);

            return $source;
        });
    }
}
