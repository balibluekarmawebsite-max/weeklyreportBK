<?php

namespace App\Exports;

use App\Services\ReportData;
use Mpdf\Mpdf;

/**
 * Renders the weekly report to a print-ready A4 PDF using an HTML (Blade)
 * template + mPDF. Header carries the property + week; footer has page numbers
 * and the confidentiality note.
 */
class PdfReportExporter
{
    public function __construct(private ReportData $data)
    {
    }

    public function save(): string
    {
        $html = view('exports.report', ['d' => $this->data])->render();

        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_top' => 28,
            'margin_bottom' => 20,
            'margin_left' => 12,
            'margin_right' => 12,
            'tempDir' => sys_get_temp_dir(),
            'default_font' => 'dejavusans',
        ]);

        $p = $this->data->property();
        $period = $this->data->week->label ?? '';
        $mpdf->SetHTMLHeader('<div style="border-bottom:1px solid #DBD0B8;padding-bottom:4px;font-size:8pt;color:#0F3D3E;">'
            .'<strong>'.e($p?->name ?? '').'</strong> &nbsp;·&nbsp; Weekly Report &nbsp;·&nbsp; '.e($period).'</div>');
        $mpdf->SetHTMLFooter('<div style="border-top:1px solid #DBD0B8;padding-top:4px;font-size:7.5pt;color:#888;">'
            .e($p?->export_footer ?? 'Confidential').' &nbsp; <span style="float:right;">Page {PAGENO} / {nbpg}</span></div>');

        $mpdf->WriteHTML($html);

        $path = tempnam(sys_get_temp_dir(), 'bkpdf_').'.pdf';
        $mpdf->Output($path, \Mpdf\Output\Destination::FILE);

        return $path;
    }
}
