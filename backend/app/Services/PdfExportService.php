<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Measurement;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfExportService
{
    public function bill(Bill $bill): string
    {
        $bill->loadMissing('project', 'creator', 'certifier', 'items.boqItem', 'payments');

        return $this->render('pdf.bill', ['bill' => $bill]);
    }

    public function measurement(Measurement $measurement): string
    {
        $measurement->loadMissing('project', 'site', 'creator', 'approver', 'items.boqItem');

        return $this->render('pdf.measurement', ['measurement' => $measurement]);
    }

    private function render(string $view, array $data): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }
}
