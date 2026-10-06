<?php

namespace App\Exports;

use App\Livewire\Sections\SocialMedia;
use App\Models\ChannelMonthRn;
use App\Services\ReportData;
use App\Support\Format;
use App\Support\ReportCalculator;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

/**
 * Builds the weekly report as an editable .docx (A4) with heading styles so the
 * table of contents works, brand-coloured header rows, and bold totals.
 */
class WordReportExporter
{
    private const INK = '15607F';

    private const GOLD = 'C9A24B';

    private const SAND = 'F4F0E7';

    private PhpWord $word;

    public function __construct(private ReportData $data)
    {
        // PHPWord does NOT escape XML special characters (& < >) in text by default,
        // so any "&" in the data (e.g. "Sales & Marketing") produced an invalid
        // document.xml that Word refused to open. Enable output escaping globally.
        Settings::setOutputEscapingEnabled(true);

        $this->word = new PhpWord;
        $this->word->getSettings()->setThemeFontLang(new Language(Language::EN_GB));
        $this->word->addTitleStyle(1, ['bold' => true, 'size' => 15, 'color' => self::INK], ['spaceBefore' => 240, 'spaceAfter' => 120]);
        $this->word->addTitleStyle(2, ['bold' => true, 'size' => 12, 'color' => self::INK], ['spaceBefore' => 160, 'spaceAfter' => 80]);
        $this->word->setDefaultFontName('Calibri');
        $this->word->setDefaultFontSize(9);
    }

    public function save(): string
    {
        $section = $this->word->addSection([
            'pageSizeW' => Converter::cmToTwip(21), 'pageSizeH' => Converter::cmToTwip(29.7),
            'marginLeft' => 720, 'marginRight' => 720, 'marginTop' => 900, 'marginBottom' => 720,
        ]);
        $p = $this->data->property();

        // Cover
        $section->addText(strtoupper('Weekly Report'), ['bold' => true, 'size' => 11, 'color' => self::GOLD], ['alignment' => Jc::CENTER, 'spaceBefore' => 2400]);
        $section->addText($p?->name ?? '', ['bold' => true, 'size' => 26, 'color' => self::INK], ['alignment' => Jc::CENTER]);
        $section->addText('Sales & Marketing', ['size' => 13, 'color' => '206E8F'], ['alignment' => Jc::CENTER, 'spaceBefore' => 200]);
        $section->addText($this->data->week->label ?? '', ['size' => 12], ['alignment' => Jc::CENTER]);
        $section->addText('Status: '.$this->data->week->status->label(), ['color' => '888888'], ['alignment' => Jc::CENTER]);
        $section->addPageBreak();

        // A — Overview
        $section->addTitle('A · Sales & Marketing Overview', 1);
        foreach ($this->data->overviewBlocks() as $b) {
            $section->addTitle($b->heading, 2);
            $section->addText($b->body ?: '–', ['size' => 9]);
        }

        // B — YTD
        $section->addTitle('B · Year to Date Actual & On-Hand Forecast', 1);
        $this->monthlyTable($section);

        // C & D
        $section->addTitle('C · Weekly Production by Market Segment', 1);
        $this->segmentTable($section);
        $section->addTitle('D · Rate Code / Promotion', 1);
        $this->productionTable($section, $this->data->rateCodes(), $this->data->rateCodeTotals(), 'Promotion');

        // E/F — channels (latest year)
        $section->addTitle('E/F · Channel Inside (Room Nights)', 1);
        $this->channelsTable($section);

        // G / G2
        $section->addTitle('G · Sales Activity', 1);
        $this->activityTable($section, $this->data->activities('sales'), 'Subject');
        $section->addTitle('G2 · E-commerce Activities', 1);
        $this->activityTable($section, $this->data->activities('ecommerce'), 'Task');

        // H — Social
        $section->addTitle('H · Social Media Insight ('.$this->data->socialPlatform().')', 1);
        $this->socialTable($section);

        // I — Training
        $section->addTitle('I · Training', 1);
        $this->trainingsTable($section);

        // J — Action Plan
        $section->addTitle('J · Next Week Action Plan', 1);
        $this->actionPlanTable($section);

        // Owner
        $section->addTitle('Owner Overview', 1);
        $this->ownerTables($section);

        $path = tempnam(sys_get_temp_dir(), 'bkword_').'.docx';
        IOFactory::createWriter($this->word, 'Word2007')->save($path);

        return $path;
    }

    // ---- tables ---------------------------------------------------------

    private function newTable($section)
    {
        return $section->addTable([
            'borderColor' => 'E9E2D2', 'borderSize' => 4, 'cellMargin' => 50, 'width' => 100 * 50, 'unit' => 'pct',
        ]);
    }

    private function hcell($row, string $text, int $width, string $align = 'right'): void
    {
        $cell = $row->addCell($width, ['bgColor' => self::INK]);
        $cell->addText($text, ['bold' => true, 'color' => 'FFFFFF', 'size' => 8], ['alignment' => $align === 'right' ? Jc::END : Jc::START]);
    }

    private function cell($row, string $text, int $width, string $align = 'right', bool $bold = false, ?string $bg = null): void
    {
        $cell = $row->addCell($width, $bg ? ['bgColor' => $bg] : []);
        $cell->addText($text === '' ? ' ' : $text, ['bold' => $bold, 'size' => 8.5], ['alignment' => $align === 'right' ? Jc::END : Jc::START]);
    }

    private function monthlyTable($section): void
    {
        $t = $this->newTable($section);
        $w = 760;
        $h = $t->addRow();
        $this->hcell($h, 'Month', 900, 'left');
        foreach (['RN', 'Occ A', 'Occ B', 'Occ LY', 'ARR A', 'ARR B', 'ARR LY', 'Rev A', 'Rev B', 'Rev LY'] as $lbl) {
            $this->hcell($h, $lbl, $w);
        }
        $stats = $this->data->monthlyStats()->keyBy('month');
        $pct = fn ($f) => $f === null ? '–' : number_format($f * 100, 1).'%';
        foreach (ReportData::MONTHS as $i => $name) {
            $m = $stats->get($i + 1);
            $r = $t->addRow();
            $this->cell($r, $name, 900, 'left');
            $this->cell($r, Format::number($m?->rn_sold), $w);
            $this->cell($r, $pct($m?->occ_actual), $w);
            $this->cell($r, $pct($m?->occ_budget), $w);
            $this->cell($r, $pct($m?->occ_ly), $w);
            $this->cell($r, Format::idr($m?->arr_actual), $w);
            $this->cell($r, Format::idr($m?->arr_budget), $w);
            $this->cell($r, Format::idr($m?->arr_ly), $w);
            $this->cell($r, Format::idr($m?->rev_actual), $w);
            $this->cell($r, Format::idr($m?->rev_budget), $w);
            $this->cell($r, Format::idr($m?->rev_ly), $w);
        }
        $tot = $this->data->monthlyTotals();
        $r = $t->addRow();
        $this->cell($r, 'Total', 900, 'left', true, self::SAND);
        $this->cell($r, Format::number($tot['rn_sold']), $w, 'right', true, self::SAND);
        foreach (['', '', ''] as $blank) {
            $this->cell($r, '', $w, 'right', true, self::SAND);
        }
        $this->cell($r, Format::idr($tot['arr_actual']), $w, 'right', true, self::SAND);
        $this->cell($r, Format::idr($tot['arr_budget']), $w, 'right', true, self::SAND);
        $this->cell($r, Format::idr($tot['arr_ly']), $w, 'right', true, self::SAND);
        $this->cell($r, Format::idr($tot['rev_actual']), $w, 'right', true, self::SAND);
        $this->cell($r, Format::idr($tot['rev_budget']), $w, 'right', true, self::SAND);
        $this->cell($r, Format::idr($tot['rev_ly']), $w, 'right', true, self::SAND);
    }

    /** Section C — grouped by category with per-group subtotals when available. */
    private function segmentTable($section): void
    {
        if (! $this->data->segmentsAreGrouped()) {
            $this->productionTable($section, $this->data->segments(), $this->data->segmentTotals(), 'Source / Segment');

            return;
        }

        $tot = $this->data->segmentTotals();
        $t = $this->newTable($section);
        $h = $t->addRow();
        $this->hcell($h, 'Source / Segment', 3500, 'left');
        foreach (['RN Sold', 'Gross Revenue', 'ARR', '%'] as $l) {
            $this->hcell($h, $l, 1600);
        }

        foreach ($this->data->segmentGroups() as $group => $g) {
            $r = $t->addRow();
            $this->cell($r, (string) $group, 3500, 'left', true, self::SAND);
            foreach (['', '', '', ''] as $blank) {
                $this->cell($r, '', 1600, 'right', true, self::SAND);
            }
            foreach ($g['rows'] as $row) {
                $rr = $t->addRow();
                $this->cell($rr, '   '.$row->label, 3500, 'left');
                $this->cell($rr, Format::number($row->rn_sold), 1600);
                $this->cell($rr, Format::idr($row->gross_revenue), 1600);
                $this->cell($rr, Format::idr($row->arr()), 1600);
                $this->cell($rr, Format::percent(ReportCalculator::sharePercent($row->rn_sold, $tot['rn'])), 1600);
            }
            $rr = $t->addRow();
            $this->cell($rr, '   '.$group.' subtotal', 3500, 'left');
            $this->cell($rr, Format::number($g['rn']), 1600);
            $this->cell($rr, Format::idr($g['revenue']), 1600);
            $this->cell($rr, '', 1600);
            $this->cell($rr, Format::percent(ReportCalculator::sharePercent($g['rn'], $tot['rn'])), 1600);
        }

        $r = $t->addRow();
        $this->cell($r, 'Total', 3500, 'left', true, self::SAND);
        $this->cell($r, Format::number($tot['rn']), 1600, 'right', true, self::SAND);
        $this->cell($r, Format::idr($tot['revenue']), 1600, 'right', true, self::SAND);
        $this->cell($r, Format::idr($tot['arr']), 1600, 'right', true, self::SAND);
        $this->cell($r, '100%', 1600, 'right', true, self::SAND);
    }

    private function productionTable($section, $rows, array $tot, string $lh): void
    {
        $t = $this->newTable($section);
        $h = $t->addRow();
        $this->hcell($h, $lh, 3500, 'left');
        foreach (['RN Sold', 'Gross Revenue', 'ARR', '%'] as $l) {
            $this->hcell($h, $l, 1600);
        }
        foreach ($rows as $r) {
            $row = $t->addRow();
            $this->cell($row, $r->label, 3500, 'left');
            $this->cell($row, Format::number($r->rn_sold), 1600);
            $this->cell($row, Format::idr($r->gross_revenue), 1600);
            $this->cell($row, Format::idr($r->arr()), 1600);
            $this->cell($row, Format::percent(ReportCalculator::sharePercent($r->rn_sold, $tot['rn'])), 1600);
        }
        $row = $t->addRow();
        $this->cell($row, 'Total', 3500, 'left', true, self::SAND);
        $this->cell($row, Format::number($tot['rn']), 1600, 'right', true, self::SAND);
        $this->cell($row, Format::idr($tot['revenue']), 1600, 'right', true, self::SAND);
        $this->cell($row, Format::idr($tot['arr']), 1600, 'right', true, self::SAND);
        $this->cell($row, '100%', 1600, 'right', true, self::SAND);
    }

    private function channelsTable($section): void
    {
        $mcols = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($this->data->channelsByYear()->sortKeysDesc()->take(1) as $year => $sources) {
            $section->addText((string) $year, ['bold' => true, 'color' => self::GOLD]);
            $grand = (int) $sources->sum(fn ($x) => $x->ytd());
            $t = $this->newTable($section);
            $h = $t->addRow();
            $this->hcell($h, 'Source', 1800, 'left');
            foreach ($mcols as $mn) {
                $this->hcell($h, $mn, 500);
            }
            $this->hcell($h, 'YTD', 700);
            foreach ($sources as $src) {
                $r = $t->addRow();
                $this->cell($r, $src->source_label, 1800, 'left');
                foreach (ChannelMonthRn::MONTHS as $mk) {
                    $this->cell($r, (string) ($src->{$mk} ?: ''), 500);
                }
                $this->cell($r, Format::number($src->ytd()), 700);
            }
            $r = $t->addRow();
            $this->cell($r, 'Total', 1800, 'left', true, self::SAND);
            foreach ($mcols as $mn) {
                $this->cell($r, '', 500, 'right', true, self::SAND);
            }
            $this->cell($r, Format::number($grand), 700, 'right', true, self::SAND);
        }
    }

    private function activityTable($section, $acts, string $lh): void
    {
        $t = $this->newTable($section);
        $h = $t->addRow();
        $this->hcell($h, 'Date', 1400, 'left');
        $this->hcell($h, $lh, 2400, 'left');
        $this->hcell($h, 'Notes / Remarks', 6000, 'left');
        if ($acts->isEmpty()) {
            $r = $t->addRow();
            $this->cell($r, 'No entries.', 9800, 'left');

            return;
        }
        foreach ($acts as $a) {
            $r = $t->addRow();
            $this->cell($r, $a->date_label ?? '', 1400, 'left');
            $this->cell($r, $a->title ?? '', 2400, 'left');
            $this->cell($r, $a->notes ?? '', 6000, 'left');
        }
    }

    private function socialTable($section): void
    {
        $labels = SocialMedia::METRICS;
        $t = $this->newTable($section);
        $h = $t->addRow();
        $this->hcell($h, 'Metric', 3000, 'left');
        foreach (['Last Week', 'This Week', 'Growth', 'Growth %'] as $l) {
            $this->hcell($h, $l, 1700);
        }
        foreach ($this->data->socialMetrics() as $m) {
            $r = $t->addRow();
            $g = $m->growth();
            $gp = $m->growthPercent();
            $this->cell($r, $labels[$m->metric_key] ?? $m->metric_key, 3000, 'left');
            $this->cell($r, Format::number($m->last_week), 1700);
            $this->cell($r, Format::number($m->this_week), 1700);
            $this->cell($r, $g === null ? '–' : ($g >= 0 ? '+' : '').Format::number($g), 1700);
            $this->cell($r, $gp === null ? '–' : ($gp >= 0 ? '+' : '').number_format($gp, 1).'%', 1700);
        }
    }

    private function trainingsTable($section): void
    {
        $t = $this->newTable($section);
        $h = $t->addRow();
        foreach ([['Date', 1400], ['Topic', 3500], ['Duration', 1200], ['Trainer', 1800], ['Participants', 2500]] as [$l, $w]) {
            $this->hcell($h, $l, $w, 'left');
        }
        foreach ($this->data->trainings() as $tr) {
            $r = $t->addRow();
            $this->cell($r, $tr->date_label ?? '', 1400, 'left');
            $this->cell($r, $tr->topic, 3500, 'left');
            $this->cell($r, $tr->duration ?? '', 1200, 'left');
            $this->cell($r, $tr->trainer ?? '', 1800, 'left');
            $this->cell($r, $tr->participants ?? '', 2500, 'left');
        }
    }

    private function actionPlanTable($section): void
    {
        $t = $this->newTable($section);
        $h = $t->addRow();
        foreach ([['Category', 1500], ['Plan', 2200], ['Start', 1300], ['Deadline', 1300], ['Remark', 4500]] as [$l, $w]) {
            $this->hcell($h, $l, $w, 'left');
        }
        foreach ($this->data->actionPlansByCategory() as $category => $plans) {
            foreach ($plans as $pl) {
                $r = $t->addRow();
                $this->cell($r, (string) $category, 1500, 'left', false, self::SAND);
                $this->cell($r, $pl->plan, 2200, 'left');
                $this->cell($r, $pl->start_label ?? '', 1300, 'left');
                $this->cell($r, $pl->deadline_label ?? '', 1300, 'left');
                $this->cell($r, $pl->remark ?? '', 4500, 'left');
            }
        }
    }

    private function ownerTables($section): void
    {
        $section->addTitle('1 · Repeater Guest — Room Performance', 2);
        $rt = $this->data->ownerRepeaterTotals();
        $t = $this->newTable($section);
        $h = $t->addRow();
        $this->hcell($h, 'Month', 3000, 'left');
        foreach (['Room Nights', 'ADR', 'Revenue'] as $l) {
            $this->hcell($h, $l, 2200);
        }
        foreach ($this->data->ownerRepeater() as $m) {
            $r = $t->addRow();
            $this->cell($r, $m->label, 3000, 'left');
            $this->cell($r, Format::number($m->room_nights), 2200);
            $this->cell($r, Format::idr($m->adr()), 2200);
            $this->cell($r, Format::idr($m->revenue), 2200);
        }
        $r = $t->addRow();
        $this->cell($r, 'Total', 3000, 'left', true, self::SAND);
        $this->cell($r, Format::number($rt['rn']), 2200, 'right', true, self::SAND);
        $this->cell($r, Format::idr($rt['adr']), 2200, 'right', true, self::SAND);
        $this->cell($r, Format::idr($rt['revenue']), 2200, 'right', true, self::SAND);

        $section->addTitle('2 · Channel Mix', 2);
        $mt = $this->data->ownerMixTotals();
        $t = $this->newTable($section);
        $h = $t->addRow();
        $this->hcell($h, 'Source', 3000, 'left');
        foreach (['RN Sold', 'ARR', 'Revenue', '%'] as $l) {
            $this->hcell($h, $l, 1700);
        }
        foreach ($this->data->ownerMix() as $m) {
            $r = $t->addRow();
            $this->cell($r, $m->label, 3000, 'left');
            $this->cell($r, Format::number($m->rn_sold), 1700);
            $this->cell($r, Format::idr($m->arr()), 1700);
            $this->cell($r, Format::idr($m->gross_revenue), 1700);
            $this->cell($r, Format::percent(ReportCalculator::sharePercent($m->rn_sold, $mt['rn'])), 1700);
        }
    }
}
