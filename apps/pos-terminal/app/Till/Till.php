<?php

declare(strict_types=1);

namespace App\Till;

use App\Api\Data\IssuedToken;
use App\Api\Data\PairingResult;
use App\Models\Category;
use App\Models\Item;
use App\Models\StaffMember;
use App\Models\SyncState;
use App\Models\TillIdentity;
use App\Security\SecretBox;
use Illuminate\Support\Facades\DB;

/**
 * Which till this is, and its device token. The token is encrypted by the OS
 * keyring before it touches the database and decrypted only to make a request.
 */
final class Till
{
    public function __construct(private readonly SecretBox $secrets) {}

    public function identity(): ?TillIdentity
    {
        return TillIdentity::query()->find(TillIdentity::ROW_ID);
    }

    public function isPaired(): bool
    {
        return $this->identity()?->status === TillIdentity::ACTIVE;
    }

    public function isRemoved(): bool
    {
        return $this->identity()?->status === TillIdentity::REMOVED;
    }

    /**
     * Device ID and plain-text token for an API request. A removed till still
     * has them: renewal needs the expired token.
     *
     * @return array{device_id: string, token: string}
     *
     * @throws TillNotPaired
     */
    public function credentials(): array
    {
        $identity = $this->identity() ?? throw new TillNotPaired('This till has not been paired.');

        return [
            'device_id' => $identity->device_id,
            'token' => $this->secrets->decrypt($identity->token_ciphertext),
        ];
    }

    /**
     * Become the till the server just paired. The menu and staff lists of any
     * earlier pairing are wiped, since they may belong to another business,
     * and are downloaded again in full.
     */
    public function pair(PairingResult $result): TillIdentity
    {
        // Encrypt first: the keyring call must not run inside the transaction.
        $ciphertext = $this->secrets->encrypt($result->token->plainText);

        return DB::transaction(function () use ($result, $ciphertext): TillIdentity {
            StaffMember::query()->delete();
            Item::query()->delete();
            Category::query()->delete();
            SyncState::query()->delete();

            return TillIdentity::query()->updateOrCreate(['id' => TillIdentity::ROW_ID], [
                'device_id' => $result->deviceId,
                'device_name' => $result->deviceName,
                'business_name' => $result->businessName,
                'branch_id' => $result->branch->id,
                'branch_name' => $result->branch->name,
                'business_type' => $result->branch->businessType,
                'terminal_mode' => $result->branch->terminalMode,
                'token_ciphertext' => $ciphertext,
                'token_expires_at' => $result->token->expiresAt,
                'status' => TillIdentity::ACTIVE,
                'paired_at' => now(),
                'removed_at' => null,
            ]);
        });
    }

    /** @throws TillNotPaired */
    public function storeToken(IssuedToken $token): void
    {
        $identity = $this->identity() ?? throw new TillNotPaired('This till has not been paired.');

        $identity->update([
            'token_ciphertext' => $this->secrets->encrypt($token->plainText),
            'token_expires_at' => $token->expiresAt,
        ]);
    }

    /**
     * The server no longer accepts this till. Nothing local is deleted; the
     * till waits to be paired again.
     */
    public function markRemoved(): void
    {
        $identity = $this->identity();

        if ($identity?->status !== TillIdentity::ACTIVE) {
            return;
        }

        $identity->update([
            'status' => TillIdentity::REMOVED,
            'removed_at' => now(),
        ]);
    }
}
