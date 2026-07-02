<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\CommandCenters\Contracts\BusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\ExecutiveBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\FleetBusinessCommandCenter;
use App\Domain\Analytics\CommandCenters\OperationsBusinessCommandCenter;
use App\Domain\Analytics\Exports\ExportEngineResolver;
use App\Domain\Analytics\Exports\ExportRequest;
use App\Http\Analytics\ExportDownloadResponse;
use App\Http\Analytics\ExportRequestValidator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP boundary for BI report exports. It orchestrates only: validate the inputs, ask the BI
 * command center for the already-translated report view, resolve the export engine, and return
 * the download. It calculates nothing, translates nothing, and queries nothing.
 */
class ExportController extends Controller
{
    public function __construct(
        private readonly ExecutiveBusinessCommandCenter $executive,
        private readonly OperationsBusinessCommandCenter $operations,
        private readonly FleetBusinessCommandCenter $fleet,
        private readonly ExportEngineResolver $resolver,
        private readonly ExportRequestValidator $validator,
    ) {
        $this->middleware('auth');
    }

    public function executive(Request $request, string $format): Response
    {
        return $this->download('executive', $this->executive, $request, $format);
    }

    public function operations(Request $request, string $format): Response
    {
        return $this->download('operations', $this->operations, $request, $format);
    }

    public function fleet(Request $request, string $format): Response
    {
        return $this->download('fleet', $this->fleet, $request, $format);
    }

    private function download(string $report, BusinessCommandCenter $center, Request $request, string $format): Response
    {
        $this->validator->report($report);
        $resolvedFormat = $this->validator->format($format);
        $requestedName = $request->query('filename');
        $filename = $this->validator->filename(is_string($requestedName) ? $requestedName : null);

        $view = $center->dashboard()->report()->view;
        $result = $this->resolver->resolve($resolvedFormat)->export(new ExportRequest($resolvedFormat, $view, $filename));

        return ExportDownloadResponse::fromResult($result);
    }
}
