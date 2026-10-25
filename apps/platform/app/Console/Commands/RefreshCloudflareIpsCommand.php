<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Network\CloudflareIps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

#[Signature('network:refresh-cloudflare-ips')]
#[Description('Fetch Cloudflare’s current edge IP ranges; the previous list stays if the answer is unusable')]
final class RefreshCloudflareIpsCommand extends Command
{
    public function handle(CloudflareIps $cloudflareIps): int
    {
        try {
            $ranges = $cloudflareIps->refresh();
        } catch (RuntimeException $e) {
            Log::channel('security')->warning('network.cloudflare_ips_refresh_failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage().' Keeping the current list.');

            return self::FAILURE;
        }

        $this->info('Trusting '.count($ranges).' Cloudflare ranges.');

        return self::SUCCESS;
    }
}
