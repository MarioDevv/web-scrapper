<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use SeoSpider\Audit\Application\GetPageDetail\{GetPageDetailQuery, GetPageDetailHandler};

final class PageController
{
    public function show(string $id, GetPageDetailHandler $handler): JsonResponse
    {
        return response()->json($handler(new GetPageDetailQuery($id)));
    }
}
