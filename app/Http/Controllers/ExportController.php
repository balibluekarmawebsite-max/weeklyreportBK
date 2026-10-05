<?php

namespace App\Http\Controllers;

use App\Exports\ExcelReportExporter;
use App\Exports\PdfReportExporter;
use App\Exports\WordReportExporter;
use App\Models\ActivityLog;
use App\Models\ReportWeek;
use App\Services\ReportData;
use App\Support\Workspace;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    public function index(): View
    {
        $property = Workspace::currentProperty();

        $weeks = ReportWeek::query()
            ->when($property, fn ($q) => $q->where('property_id', $property->id))
            ->orderByDesc('start_date')
            ->take(20)
            ->get();

        return view('exports.index', [
            'property' => $property,
            'weeks' => $weeks,
        ]);
    }

    public function excel(ReportWeek $reportWeek): BinaryFileResponse
    {
        return $this->download($reportWeek, 'excel');
    }

    public function pdf(ReportWeek $reportWeek): BinaryFileResponse
    {
        return $this->download($reportWeek, 'pdf');
    }

    public function word(ReportWeek $reportWeek): BinaryFileResponse
    {
        return $this->download($reportWeek, 'word');
    }

    private function download(ReportWeek $reportWeek, string $format): BinaryFileResponse
    {
        $data = new ReportData($reportWeek);

        [$path, $ext] = match ($format) {
            'pdf' => [(new PdfReportExporter($data))->save(), 'pdf'],
            'word' => [(new WordReportExporter($data))->save(), 'docx'],
            default => [(new ExcelReportExporter($data))->save(), 'xlsx'],
        };

        $reportWeek->forceFill(['exported_at' => now()])->save();
        ActivityLog::record('exported', $reportWeek, 'Exported '.strtoupper($format).' for '.$reportWeek->label);

        return response()->download($path, $data->filename($ext))->deleteFileAfterSend(true);
    }
}
