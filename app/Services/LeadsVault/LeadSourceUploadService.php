<?php

namespace App\Services\LeadsVault;

use App\Models\LeadSource;
use App\Jobs\ImportLeadSourceJob;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File as HttpFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class LeadSourceUploadService
{
    public function handle(array $files, array $mapping, ?int $userId): array
    {
        $tenantUuid = TenantStorage::requireTenantUuid();

        $created = [];

        foreach ($files as $file) {

            if (!$file || !$file->isValid()) {
                continue;
            }

            /* ======================================================
               TRANSACTION → SOMENTE BD
            ====================================================== */

            $source = DB::transaction(function () use ($file, $mapping, $userId, $tenantUuid) {

                $original = $file->getClientOriginalName();

                $safeName = Str::slug(pathinfo($original, PATHINFO_FILENAME));
                $ext      = strtolower($file->getClientOriginalExtension());

                $storedFile = $file;
                $storeExt   = $ext;
                $tmpPath    = null;

                if (in_array($ext, ['xlsx','xls'], true)) {
                    $tmpPath    = $this->convertSpreadsheetToCsv($file);
                    $storedFile = new HttpFile($tmpPath);
                    $storeExt   = 'csv';
                }

                $filename = Str::uuid().'_'.$safeName.'.'.$storeExt;

                $dir = TenantStorage::importsDir($tenantUuid);

                try {
                    if ($storedFile instanceof UploadedFile) {
                        if (!TenantStorage::putPrivateFileAs($dir, $storedFile, $filename)) {
                            throw new RuntimeException('Falha ao salvar arquivo.');
                        }
                    } else {
                        TenantStorage::ensurePrivateDir($dir);
                        Storage::disk('private')->putFileAs($dir, $storedFile, $filename);

                        $path = TenantStorage::normalizePath($dir . '/' . $filename);
                        TenantStorage::fixPrivateDirPermsForFile($path);
                        TenantStorage::fixPrivateFilePerms($path);
                    }
                } finally {
                    if ($tmpPath && is_file($tmpPath)) {
                        @unlink($tmpPath);
                    }
                }

                $relative = "{$dir}/{$filename}";
                $absPath  = TenantStorage::privateAbsolutePath($relative);

                return LeadSource::create([
                    'tenant_uuid'     => $tenantUuid,
                    'original_name'   => $original,
                    'file_path'       => $relative,
                    'file_ext'        => $storeExt,
                    'file_size_bytes' => filesize($absPath),
                    'file_hash'       => sha1_file($absPath),
                    'status'          => 'queued',
                    'mapping_json'    => $mapping,
                    'created_by'      => $userId,
                ]);
            });

            /* ======================================================
   JOB (NÃO usar afterCommit)
====================================================== */

ImportLeadSourceJob::dispatch($source->id)
    ->onQueue('imports')
    ->afterCommit(); // ⭐⭐⭐ ESSENCIAL


            $created[] = [
                'id'   => $source->id,
                'name' => $source->original_name,
            ];
        }

        return $created;
    }

    private function convertSpreadsheetToCsv(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if (!$path || !is_file($path)) {
            throw new RuntimeException('Falha ao ler arquivo XLSX/XLS.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $writer = IOFactory::createWriter($spreadsheet, 'Csv');
        $writer->setDelimiter(',');
        $writer->setEnclosure('"');
        $writer->setSheetIndex(0);

        $tmpPath = tempnam(sys_get_temp_dir(), 'xls2csv_');
        if (!$tmpPath) {
            throw new RuntimeException('Falha ao criar arquivo temporário.');
        }

        $writer->save($tmpPath);

        return $tmpPath;
    }
}
