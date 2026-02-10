<?php

namespace App\Jobs;

use App\Models\LeadSource;
use App\Services\LeadsVault\OperationalCatalogSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class NormalizeLeadsJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;
    public array $backoff = [2, 8, 20];

    public function __construct(
        public string $tenantUuid,
        public int $leadSourceId
    ){
        $this->onQueue('normalize');
    }

    public function handle(OperationalCatalogSyncService $catalogSync): void
    {
        $source = LeadSource::query()->find($this->leadSourceId);
        if ($source) {
            $source->update([
                'status' => 'normalizing',
                'last_error' => null,
            ]);
        }

        try {
        /*
        |--------------------------------------------------------------------------
        | Grade NORMALIZE – FAST PATH (1 PASS SQL)
        |--------------------------------------------------------------------------
        | ✔ Sem JOIN
        | ✔ Sem cache
        | ✔ Bulk insert
        | ✔ UF protegida contra overflow
        |--------------------------------------------------------------------------
        */

        $this->runWithDeadlockRetry(function () {
            DB::statement("
            INSERT INTO leads_normalized (
                tenant_uuid,
                lead_source_id,
                row_num,

                name,
                cpf,
                phone_e164,
                email,

                city,
                uf,
                sex,

                score,
                extras_json,
                created_at,
                updated_at
            )

            SELECT
                r.tenant_uuid,
                r.lead_source_id,
                r.row_num,

                r.name,
                r.cpf,
                r.phone_e164,
                r.email,

                /* =========================
                   CITY
                ========================== */
                NULLIF(
                    TRIM(
                        COALESCE(
                            JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.CIDADE')),
                            JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.Cidade')),
                            JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.city')),
                            JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.MUNICIPIO')),
                            JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.\\\"ï»¿CIDADE\\\"'))
                        )
                    ),
                    ''
                ) AS city,

                /* =========================
                   UF (SAFE 2 CHARS)
                ========================== */
                NULLIF(
                    LEFT(
                        UPPER(
                            TRIM(
                                COALESCE(
                                    JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.ESTADO')),
                                    JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.Estado')),
                                    JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.UF')),
                                    JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.uf')),
                                    JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.\\\"ï»¿ESTADO\\\"')),
                                    JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.\\\"ï»¿UF\\\"'))
                                )
                            )
                        ),
                        2
                    ),
                    ''
                ) AS uf,

                /* =========================
                   SEX
                ========================== */
                CASE
                    WHEN LOWER(COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.GENERO')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.Genero')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.SEXO')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.Sexo')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.\\\"ï»¿GENERO\\\"')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.\\\"ï»¿SEXO\\\"'))
                    )) LIKE 'm%' THEN 'M'
                    WHEN LOWER(COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.GENERO')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.Genero')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.SEXO')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.Sexo')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.\\\"ï»¿GENERO\\\"')),
                        JSON_UNQUOTE(JSON_EXTRACT(r.payload_json,'$.\\\"ï»¿SEXO\\\"'))
                    )) LIKE 'f%' THEN 'F'
                    ELSE NULL
                END AS sex,

                0,
                r.payload_json,
                NOW(),
                NOW()

            FROM lead_raw r
            WHERE r.tenant_uuid = ?
              AND r.lead_source_id = ?

            ON DUPLICATE KEY UPDATE
                name        = VALUES(name),
                cpf         = VALUES(cpf),
                phone_e164  = VALUES(phone_e164),
                email       = VALUES(email),
                city        = VALUES(city),
                uf          = VALUES(uf),
                sex         = VALUES(sex),
                extras_json = VALUES(extras_json),
                updated_at  = NOW()
            ", [
                $this->tenantUuid,
                $this->leadSourceId
            ]);
        });

            if ($source) {
                $source->update([
                    'status' => 'done',
                    'last_error' => null,
                    'finished_at' => now(),
                ]);
            }

            $catalogSyncWarning = null;
            try {
                $this->runWithDeadlockRetry(function () use ($catalogSync) {
                    $catalogSync->syncBySourceId($this->leadSourceId, $this->tenantUuid);
                });
            } catch (\Throwable $syncError) {
                report($syncError);
                $catalogSyncWarning = 'Sync operacional parcial: ' . $syncError->getMessage();
            }

            if ($source && $catalogSyncWarning !== null) {
                $source->update([
                    'last_error' => $catalogSyncWarning,
                ]);
            }
        } catch (\Throwable $e) {
            if ($source) {
                $source->update([
                    'status' => 'failed',
                    'last_error' => $e->getMessage(),
                    'finished_at' => now(),
                ]);
            }
            throw $e;
        }
    }

    private function runWithDeadlockRetry(callable $callback, int $maxAttempts = 3): void
    {
        $attempt = 0;
        beginning:
        $attempt++;

        try {
            $callback();
        } catch (\Throwable $e) {
            if ($attempt < $maxAttempts && $this->isDeadlock($e)) {
                usleep((int) (150000 * $attempt));
                goto beginning;
            }
            throw $e;
        }
    }

    private function isDeadlock(\Throwable $e): bool
    {
        if ($e instanceof QueryException) {
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if ($sqlState === '40001' || $driverCode === 1213) {
                return true;
            }
        }

        $message = strtolower($e->getMessage());
        return str_contains($message, 'deadlock')
            || str_contains($message, 'serialization failure');
    }

    public function failed(\Throwable $e): void
    {
        $source = LeadSource::query()->find($this->leadSourceId);
        if (!$source) {
            return;
        }

        $status = (string) ($source->status ?? '');
        if (in_array($status, ['done', 'failed', 'cancelled'], true)) {
            return;
        }

        $source->update([
            'status' => 'failed',
            'last_error' => $e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
