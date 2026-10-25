<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Tenancy\Models\TenantExport;
use App\Infrastructure\Exports\StreamCipher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a verified export to a platform admin, decrypted on the fly (the
 * plaintext never touches the disk). Registered inside the Admin panel, so
 * the panel's sign-in, admin role and authenticator checks apply. Every
 * download is written to the security log.
 */
final class DownloadTenantExport
{
    public function __invoke(Request $request, TenantExport $export, StreamCipher $cipher): StreamedResponse
    {
        abort_unless($export->isVerified() && $export->disk !== null && $export->path !== null, 404);

        $source = Storage::disk($export->disk)->path($export->path);

        Log::channel('security')->info('admin.tenant_export_downloaded', [
            'export_id' => $export->id,
            'tenant_id' => $export->tenant_id,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip' => $request->ip(),
        ]);

        $filename = sprintf('%s-%s.dump', $export->tenant_id, $export->created_at->format('Ymd-His'));

        return response()->streamDownload(function () use ($cipher, $source) {
            $cipher->decrypt($source, function (string $chunk) {
                echo $chunk;
                flush();
            });
        }, $filename, ['Content-Type' => 'application/octet-stream']);
    }
}
