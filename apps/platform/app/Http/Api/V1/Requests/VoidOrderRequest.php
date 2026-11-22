<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Requests;

use VendaPOS\Core\Data\VoidData;

final class VoidOrderRequest extends ApiRequest
{
    public function rules(): array
    {
        return VoidData::rules();
    }
}
