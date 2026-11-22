<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Requests;

/**
 * Only the batch envelope is validated here. Each reprint is validated on its
 * own in the controller, so one bad entry is rejected without failing the batch.
 */
final class SyncReprintsRequest extends ApiRequest
{
    public const int MAX_BATCH = 50;

    public function rules(): array
    {
        return [
            'reprints' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'reprints.*' => ['array'],
        ];
    }
}
