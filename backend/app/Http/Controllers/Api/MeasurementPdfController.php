<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Measurement;
use App\Services\AuditLogService;
use App\Services\PdfExportService;
use Illuminate\Http\Request;

class MeasurementPdfController extends Controller
{
    public function __construct(
        private readonly PdfExportService $pdf,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function __invoke(Request $request, Measurement $measurement)
    {
        $this->authorize('view', $measurement);
        abort_unless($request->user()->hasPermission('measurements.view'), 403);

        $bytes = $this->pdf->measurement($measurement);
        $this->auditLog->log('measurement.pdf_exported', $measurement, $request->user());

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="measurement-'.$measurement->uuid.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
