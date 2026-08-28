<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Services\AuditLogService;
use App\Services\PdfExportService;
use Illuminate\Http\Request;

class BillPdfController extends Controller
{
    public function __construct(
        private readonly PdfExportService $pdf,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function __invoke(Request $request, Bill $bill)
    {
        $this->authorize('view', $bill);
        abort_unless($request->user()->hasPermission('billing.view'), 403);

        $bytes = $this->pdf->bill($bill);
        $this->auditLog->log('bill.pdf_exported', $bill, $request->user());

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="bill-'.$bill->bill_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
