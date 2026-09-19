<?php

declare(strict_types=1);

namespace App\Http\Responses;

class JsonResponse extends \Illuminate\Http\JsonResponse
{
    public function __construct($data = null, int $status = 200, array $headers = [], int $options = 0, bool $json = false)
    {
        parent::__construct($data, $status, $headers, $options | JSON_PRESERVE_ZERO_FRACTION, $json);
    }
}
