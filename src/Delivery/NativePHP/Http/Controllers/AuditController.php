<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use SeoSpider\Audit\Application\StartAudit\{StartAuditCommand, StartAuditHandler};
use SeoSpider\Audit\Application\GetAuditStatus\{GetAuditStatusQuery, GetAuditStatusHandler};
use SeoSpider\Audit\Application\GetAuditPages\{GetAuditPagesQuery, GetAuditPagesHandler};
use SeoSpider\Audit\Application\PauseAudit\{PauseAuditCommand, PauseAuditHandler};
use SeoSpider\Audit\Application\ResumeAudit\{ResumeAuditCommand, ResumeAuditHandler};
use SeoSpider\Audit\Application\CancelAudit\{CancelAuditCommand, CancelAuditHandler};

final class AuditController
{
    public function start(Request $request, StartAuditHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'url'         => 'required|url',
            'max_pages'   => 'integer|min:1|max:10000',
            'max_depth'   => 'integer|min:1|max:50',
            'concurrency' => 'integer|min:1|max:20',
            'delay'       => 'numeric|min:0',
        ]);

        $response = $handler(new StartAuditCommand(
            seedUrl:      $validated['url'],
            maxPages:     (int) ($validated['max_pages'] ?? 500),
            maxDepth:     (int) ($validated['max_depth'] ?? 10),
            concurrency:  (int) ($validated['concurrency'] ?? 5),
            requestDelay: (float) ($validated['delay'] ?? 0.25),
        ));

        return response()->json($response);
    }

    public function status(string $id, GetAuditStatusHandler $handler): JsonResponse
    {
        return response()->json($handler(new GetAuditStatusQuery($id)));
    }

    public function pages(string $id, GetAuditPagesHandler $handler): JsonResponse
    {
        return response()->json($handler(new GetAuditPagesQuery($id)));
    }

    public function pause(string $id, PauseAuditHandler $handler): JsonResponse
    {
        $handler(new PauseAuditCommand($id));
        return response()->json(['status' => 'paused']);
    }

    public function resume(string $id, ResumeAuditHandler $handler): JsonResponse
    {
        $handler(new ResumeAuditCommand($id));
        return response()->json(['status' => 'resumed']);
    }

    public function cancel(string $id, CancelAuditHandler $handler): JsonResponse
    {
        $handler(new CancelAuditCommand($id));
        return response()->json(['status' => 'cancelled']);
    }
}
