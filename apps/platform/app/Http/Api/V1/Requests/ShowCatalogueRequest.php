<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Requests;

final class ShowCatalogueRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // ISO 8601 timestamp from a previous response's `synced_at`.
            'updated_since' => ['sometimes', 'date'],
        ];
    }
}
