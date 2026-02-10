<?php



namespace App\Support;



use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Str;



class TenantStorage

{
    /** @var array<string,bool> */
    private static array $guestUuidCache = [];

    /**

     * ==========================================================

     * Grade • TenantStorage (FULL FINAL CANON V5+)

     *

     * ✅ Disk private root físico:

     *    storage/app/private/tenants

     *

     * ✅ DB file_path SEMPRE salvo assim (SEM prefixo tenants/):

     *   {uuid}/imports/...

     *   {uuid}/imports/...

     *

     * ✅ Storage real usa exatamente o mesmo path.

     *

     * ✅ TENANT ONLY (workspace removido)

     * ==========================================================

     */



    /* ==========================================================

     | ✅ TENANT RESOLUTION (ÚNICA VERDADE)

     ========================================================== */



    public static function tenantUuidOrNull(): ?string

    {
        // 0) Runtime binding explícito do middleware (prioridade máxima)
        try {
            if (app()->bound('tenant_uuid')) {
                $bound = app('tenant_uuid');
                if (is_string($bound) && trim($bound) !== '') return trim($bound);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // 0.1) Override de tenant para superadmin (sessão)
        try {
            $override = session('tenant_uuid_override');
            if (is_string($override) && trim($override) !== '') return trim($override);
        } catch (\Throwable $e) {
            // ignore
        }

        // 1) TenantContext (se existir)

        try {

            if (class_exists(\App\Support\TenantContext::class)) {

                if (method_exists(\App\Support\TenantContext::class, 'tenantUuidOrNull')) {

                    $uuid = \App\Support\TenantContext::tenantUuidOrNull();

                    if (is_string($uuid) && trim($uuid) !== '') return trim($uuid);

                }



                if (method_exists(\App\Support\TenantContext::class, 'uuid')) {

                    $uuid = \App\Support\TenantContext::uuid();

                    if (is_string($uuid) && trim($uuid) !== '') return trim($uuid);

                }

            }

        } catch (\Throwable $e) {

            // ignore

        }



        // 2) Auth user

        try {

            if (function_exists('auth') && auth()->check()) {

                $u = auth()->user();



                $uuid = (string)($u->tenant_uuid ?? '');

                if (trim($uuid) !== '') return trim($uuid);

            }

        } catch (\Throwable $e) {

            // ignore

        }



        // 3) Session fallback (legado)

        try {

            $sid = session('tenant_uuid');

            if (is_string($sid) && trim($sid) !== '') return trim($sid);

        } catch (\Throwable $e) {

            // ignore

        }



        // 4) ✅ CLI fallback (Tinker / Jobs)

        try {

            $env = env('TENANT_UUID_DEFAULT');

            if (is_string($env) && trim($env) !== '') return trim($env);

        } catch (\Throwable $e) {

            // ignore

        }



        return null;

    }



    public static function requireTenantUuid(): string

    {

        $uuid = self::tenantUuidOrNull();



        if (!$uuid) {

            throw new \RuntimeException('Tenant inválido (tenant_uuid ausente). Faça logout/login e tente novamente.');

        }



        if ($uuid === 'tenant_unknown') {

            throw new \RuntimeException('Tenant inválido (tenant_unknown). Faça logout/login e tente novamente.');

        }



        return $uuid;

    }



    /* ==========================================================

     | ✅ PATH NORMALIZATION

     ========================================================== */



    public static function normalizePath(?string $path): string

    {

        $path = (string)$path;



        $path = trim($path);

        $path = str_replace('\\', '/', $path);



        $path = preg_replace('#/+#', '/', $path) ?? $path;

        $path = preg_replace('#^\./+#', '', $path) ?? $path;



        return ltrim($path, '/');

    }



    public static function stripTenantsPrefix(string $path): string

    {

        $path = self::normalizePath($path);



        if (Str::startsWith($path, 'tenants/')) {

            $path = substr($path, strlen('tenants/'));

            $path = self::normalizePath($path);

        }



        return $path;

    }



    public static function normalizeLegacyPath(?string $path): string

    {

        $path = self::normalizePath($path);



        if (Str::startsWith($path, 'tenant_unknown/')) {

            $path = substr($path, strlen('tenant_unknown/'));

            $path = self::normalizePath($path);

        }



        return self::stripTenantsPrefix($path);

    }

    private static function isGuestTenantUuid(string $tenantUuid): bool

    {

        $tenantUuid = trim($tenantUuid);

        if ($tenantUuid === '') return false;



        if (array_key_exists($tenantUuid, self::$guestUuidCache)) {

            return self::$guestUuidCache[$tenantUuid];

        }



        try {

            $sessionGuest = trim((string) session('guest_tenant_uuid', ''));

            if ($sessionGuest !== '' && $sessionGuest === $tenantUuid) {

                self::$guestUuidCache[$tenantUuid] = true;

                return true;

            }

        } catch (\Throwable $e) {

            // ignore

        }



        try {

            if (Schema::hasTable('guest_sessions')) {

                $exists = DB::table('guest_sessions')

                    ->where('guest_uuid', $tenantUuid)

                    ->exists();



                self::$guestUuidCache[$tenantUuid] = (bool) $exists;

                return (bool) $exists;

            }

        } catch (\Throwable $e) {

            // ignore

        }



        self::$guestUuidCache[$tenantUuid] = false;

        return false;

    }



    /* ==========================================================

     | ✅ ROOTS / DIRS

     ========================================================== */



    public static function tenantRoot(string $tenantUuid): string

    {
        $tenantUuid = self::normalizePath($tenantUuid);

        if ($tenantUuid === '') return '';

        if (self::isGuestTenantUuid($tenantUuid)) {

            return self::normalizePath('tenants_guest/' . $tenantUuid);

        }

        return self::normalizePath($tenantUuid);

    }



    public static function importsDir(string $tenantUuid): string

    {

        return self::normalizePath(self::tenantRoot($tenantUuid) . "/imports");

    }



    public static function backupsDir(string $tenantUuid): string

    {

        return self::normalizePath(self::tenantRoot($tenantUuid) . "/backups");

    }



    /* ==========================================================

     | ✅ ENSURE DIR

     ========================================================== */



    public static function ensurePrivateDir(string $dir): void

    {

        $dir = self::normalizePath($dir);

        if ($dir === '') return;



        try {

            if (!Storage::disk('private')->exists($dir)) {

                Storage::disk('private')->makeDirectory($dir);

            }

        } catch (\Throwable $e) {

            try {

                Storage::disk('private')->makeDirectory($dir);

            } catch (\Throwable $x) {

                // ignore

            }

        }

    }



    /* ==========================================================

     | ✅ Permissions (best-effort, Canon)

     ========================================================== */



    public static function fixPrivateDirPermsForFile(string $relativeFilePath): void

    {

        try {

            $relativeFilePath = self::normalizeLegacyPath($relativeFilePath);

            $dir = dirname($relativeFilePath);



            if ($dir === '.' || $dir === '') return;



            @chmod(Storage::disk('private')->path($dir), 0775);

        } catch (\Throwable $e) {

            // ignore

        }

    }



    public static function fixPrivateFilePerms(string $relativeFilePath): void

    {

        try {

            $relativeFilePath = self::normalizeLegacyPath($relativeFilePath);

            @chmod(Storage::disk('private')->path($relativeFilePath), 0664);

        } catch (\Throwable $e) {

            // ignore

        }

    }



    /* ==========================================================

     | ✅ PUT FILE (CANON)

     ========================================================== */



    public static function putPrivateFileAs(string $dir, \Illuminate\Http\UploadedFile $file, string $filename): bool

    {

        $dir = self::normalizePath($dir);

        $filename = trim($filename);



        if ($filename === '') return false;



        self::ensurePrivateDir($dir);



        try {

            Storage::disk('private')->putFileAs($dir, $file, $filename);



            $path = self::normalizePath($dir . '/' . $filename);

            self::fixPrivateDirPermsForFile($path);

            self::fixPrivateFilePerms($path);



            return true;

        } catch (\Throwable $e) {

            return false;

        }

    }



    public static function privateAbsolutePath(string $relativePath): string

    {

        $relativePath = self::normalizeLegacyPath($relativePath);

        return Storage::disk('private')->path($relativePath);

    }

    /* ==========================================================
     | ✅ DELETE FILE (PRIVATE DISK)
     ========================================================== */
    public static function deletePrivate(string $relativePath): bool
    {
        $relativePath = self::normalizeLegacyPath($relativePath);

        if ($relativePath === '') {
            return false;
        }

        try {
            if (Storage::disk('private')->exists($relativePath)) {
                Storage::disk('private')->delete($relativePath);
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }


    /* ==========================================================

     | ✅ WIZARD STAGING

     ========================================================== */



    public static function wizardStoreUploadToPrivateImports(\Illuminate\Http\UploadedFile $file): array

    {

        $tenantUuid = self::requireTenantUuid();



        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');



        $filename = 'wiz_' . now()->format('Ymd_His') . '_' . Str::random(6) . '__' . ($safeName ?: 'upload') . '.' . $ext;



        $stagingPath = self::normalizePath($tenantUuid . '/imports/' . $filename);



        self::ensurePrivateDir(dirname($stagingPath));



        Storage::disk('private')->putFileAs(

            dirname($stagingPath),

            $file,

            basename($stagingPath)

        );



        self::fixPrivateDirPermsForFile($stagingPath);

        self::fixPrivateFilePerms($stagingPath);



        return [

            'disk'          => 'private',

            'path'          => $stagingPath,

            'original_name' => $file->getClientOriginalName(),

            'size'          => (int) Storage::disk('private')->size($stagingPath),

            'ext'           => $ext,

        ];

    }

}
