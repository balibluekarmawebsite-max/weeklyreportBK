<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Exports\ExcelReportExporter;
use App\Exports\WordReportExporter;
use App\Models\ReportWeek;
use App\Models\User;
use App\Services\ReportData;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('email', 'ota@bluekarmasecrets.com')->firstOrFail());
    }

    private function week(): ReportWeek
    {
        return ReportWeek::where('status', ReportStatus::InProgress->value)->firstOrFail();
    }

    public function test_export_center_lists_weeks(): void
    {
        $this->get(route('exports.index'))->assertOk()->assertSee('Export Center');
    }

    public function test_excel_pdf_word_downloads_succeed(): void
    {
        $week = $this->week();

        $xlsx = $this->get(route('exports.excel', $week));
        $xlsx->assertOk();
        $this->assertStringContainsString('.xlsx', $xlsx->headers->get('content-disposition'));

        $pdf = $this->get(route('exports.pdf', $week));
        $pdf->assertOk();
        $this->assertStringContainsString('.pdf', $pdf->headers->get('content-disposition'));

        $word = $this->get(route('exports.word', $week));
        $word->assertOk();
        $this->assertStringContainsString('.docx', $word->headers->get('content-disposition'));

        // Exporting stamps the week.
        $this->assertNotNull($week->fresh()->exported_at);
    }

    public function test_word_document_is_valid_ooxml(): void
    {
        // A download succeeding over HTTP does not mean the .docx is valid: an
        // unescaped "&" once produced a file that downloaded fine but Word refused
        // to open. Build the document and load it back — a malformed document.xml
        // throws here.
        $path = (new WordReportExporter(new ReportData($this->week())))->save();

        $doc = WordIOFactory::load($path, 'Word2007');
        $this->assertNotEmpty($doc->getSections());

        // The source data contains "&" (e.g. "Sales & Marketing"); make sure it is
        // present and that reading the document did not choke on it.
        $xml = $this->documentXml($path);
        $this->assertStringContainsString('Sales &amp; Marketing', $xml);
        $this->assertStringNotContainsString('Sales & Marketing', $xml);

        @unlink($path);
    }

    private function documentXml(string $docxPath): string
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($docxPath) === true, 'docx is not a valid zip archive');
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertNotFalse($xml, 'word/document.xml missing from docx');

        return $xml;
    }

    public function test_excel_contains_correct_october_figures(): void
    {
        // Build the workbook directly and read it back.
        $path = (new ExcelReportExporter(new ReportData($this->week())))->save();
        $book = IOFactory::load($path);
        $sm1 = $book->getSheetByName('SM.1');

        // Row 14 = October (rows 5..16 are Jan..Dec). BKDS Oct: RN 410, occ 0.7348.
        $this->assertSame('October', $sm1->getCell('A14')->getValue());
        $this->assertSame(410, (int) $sm1->getCell('B14')->getValue());
        $this->assertEqualsWithDelta(0.7348, (float) $sm1->getCell('C14')->getValue(), 0.0001);

        @unlink($path);
    }
}
