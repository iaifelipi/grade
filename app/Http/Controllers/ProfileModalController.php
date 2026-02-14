<?php

namespace App\Http\Controllers;

use App\Services\Users\UsernameService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileModalController extends Controller
{
    public function update(Request $request, UsernameService $usernameService): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:64'],
            'avatar' => [
                'nullable',
                'file',
                'image',
                'max:10240', // 10MB
                'mimes:jpg,jpeg,png,webp,avif',
                'dimensions:min_width=128,min_height=128,max_width=4096,max_height=4096',
            ],
        ]);

        $user->name = trim((string) $data['name']);

        $rawUsername = isset($data['username']) ? trim((string) $data['username']) : '';
        if ($rawUsername === '') {
            $user->username = $usernameService->generateUniqueFromEmail((string) ($user->email ?? ''), $user->id);
        } else {
            $user->username = $usernameService->ensureUnique($rawUsername, $user->id);
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $tenantUuid = trim((string) ($user->tenant_uuid ?? ''));
            if ($tenantUuid === '') {
                $tenantUuid = 'global';
            }

            $baseDir = 'tenants/' . $tenantUuid . '/users/' . $user->id . '/avatars';
            Storage::disk('public')->deleteDirectory($baseDir);
            $path = $this->normalizeAndStoreAvatar($file, $baseDir);
            $user->avatar_path = $path;
        }

        $user->save();

        if ($request->expectsJson() || $request->ajax()) {
            $displayName = trim((string) ($user->name ?? 'User'));
            $parts = preg_split('/\s+/', $displayName) ?: [];
            $initials = collect($parts)
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_strtoupper(mb_substr((string) $part, 0, 1)))
                ->implode('');

            $avatarUrl = null;
            $avatarPath = trim((string) ($user->avatar_path ?? ''));
            if ($avatarPath !== '') {
                $avatarUrl = asset('storage/' . ltrim($avatarPath, '/'));
            }

            return response()->json([
                'ok' => true,
                'user' => [
                    'name' => $displayName,
                    'username' => (string) ($user->username ?? ''),
                    'handle' => '@' . ((string) ($user->username ?? ('u' . $user->id))),
                    'initials' => $initials !== '' ? $initials : 'U',
                    'avatar_url' => $avatarUrl,
                ],
            ]);
        }

        return back()->with('status', 'profile-updated');
    }

    private function normalizeAndStoreAvatar(UploadedFile $file, string $baseDir): string
    {
        $source = $this->createImageFromUpload($file);
        if (!$source) {
            abort(422, 'Formato de imagem não suportado.');
        }

        $source = $this->applyExifOrientation($source, $file);

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $side = min($srcW, $srcH);
        $srcX = (int) floor(($srcW - $side) / 2);
        $srcY = (int) floor(($srcH - $side) / 2);

        $crop = imagecrop($source, [
            'x' => $srcX,
            'y' => $srcY,
            'width' => $side,
            'height' => $side,
        ]);

        if (!$crop) {
            imagedestroy($source);
            abort(422, 'Não foi possível processar a imagem enviada.');
        }

        $size = 512;
        $final = imagecreatetruecolor($size, $size);
        imagealphablending($final, false);
        imagesavealpha($final, true);
        $transparent = imagecolorallocatealpha($final, 0, 0, 0, 127);
        imagefill($final, 0, 0, $transparent);

        imagecopyresampled($final, $crop, 0, 0, 0, 0, $size, $size, $side, $side);

        ob_start();
        $ok = imagewebp($final, null, 86);
        $binary = ob_get_clean();

        imagedestroy($source);
        imagedestroy($crop);
        imagedestroy($final);

        if (!$ok || !is_string($binary) || $binary === '') {
            abort(422, 'Não foi possível converter a imagem.');
        }

        $path = trim($baseDir, '/') . '/avatar.webp';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    private function createImageFromUpload(UploadedFile $file)
    {
        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        $path = $file->getRealPath();
        if (!$path) {
            return false;
        }

        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default => false,
        };
    }

    private function applyExifOrientation($image, UploadedFile $file)
    {
        $mime = strtolower((string) ($file->getMimeType() ?? ''));
        if ($mime !== 'image/jpeg' && $mime !== 'image/jpg') {
            return $image;
        }

        $path = $file->getRealPath();
        if (!$path || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        if ($rotated && $rotated !== $image) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }
}
