<?php

namespace App\Exports;

use App\Livewire\Sections\SocialMedia;
use App\Models\ChannelMonthRn;
use App\Services\ReportData;
use App\Support\ReportCalculator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the weekly report as a styled .xlsx — one tab per section, branded
 * header rows, right-aligned numbers with IDR / % formats, bold totals, and
 * "–" for missing values (never error cells).
 */
class ExcelReportExporter
{
    private const INK = '0F3D3E';

    private const GOLD = 'C9A24B';

    private const SAND = 'F4F0E7';

    private const MONEY = '#,##0;[Red]-#,##0';

    private const PCT = '0.0%';

    private Spreadsheet $book;

    public function __construct(private ReportData $data)
    {
        $this->book = new Spreadsheet;
        $this->book->removeSheetByIndex(0);
    }

    /** Write the workbook to a temp file and return its path. */
    public function save(): string
    {
        $this->cover();
        $this->overview();
        $this->monthly();
        $this->segmentSheet();
        $this->production('D · Rate Code / Promotion', 'D-RateCode', $this->data->rateCodes(), $this->data->rateCodeTotals(), 'Promotion');
        $this->channels();
        $this->activitiesSheet();
        $this->social();
        $this->trainingsSheet();
        $this->actionPlanSheet();
        $this->ownerOverview();

        $this->book->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'bkexp_').'.xlsx';
        (new Xlsx($this->book))->save($path);

        return $path;
    }

    // ---- sheets ---------------------------------------------------------

    private function cover(): void
    {
        $s = $this->sheet('Cover');
        $p = $this->data->property();
        $s->getColumnDimension('A')->setWidth(4);
        $s->getColumnDimension('B')->setWidth(70);
        $s->setCellValue('B3', 'WEEKLY REPORT');
        $s->getStyle('B3')->getFont()->setBold(true)->setSize(28)->getColor()->setRGB(self::INK);
        $s->setCellValue('B5', strtoupper($p?->name ?? ''));
        $s->getStyle('B5')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB(self::GOLD);
        $s->setCellValue('B7', 'Sales & Marketing');
        $s->setCellValue('B9', 'Period: '.($this->data->week->label ?? ''));
        $s->getStyle('B9')->getFont()->setSize(12);
        $s->setCellValue('B11', 'Status: '.$this->data->week->status->label());
        $s->setCellValue('B13', $p?->export_footer ?? 'Confidential');
        $s->getStyle('B13')->getFont()->setItalic(true)->getColor()->setRGB('888888');
    }

    private function overview(): void
    {
        $s = $this->sheet('SM');
        $s->getColumnDimension('A')->setWidth(4);
        $s->getColumnDimension('B')->setWidth(110);
        $this->title($s, 'B2', 'A · Sales & Marketing Overview');
        $r = 4;
        foreach ($this->data->overviewBlocks() as $block) {
            $s->setCellValue("B{$r}", $block->heading);
            $s->getStyle("B{$r}")->getFont()->setBold(true)->getColor()->setRGB(self::INK);
            $r++;
            $s->setCellValue("B{$r}", $block->body ?: '–');
            $s->getStyle("B{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $s->getRowDimension($r)->setRowHeight(-1);
            $r += 2;
        }
    }

    private function monthly(): void
    {
        $s = $this->sheet('SM.1');
        $this->title($s, 'A1', 'B · Year to Date Actual & On-Hand Forecast — '.($this->data->week->label ?? ''));
        // Grouped header
        $s->setCellValue('A3', 'Month');
        $s->setCellValue('B3', 'RN Sold');
        $s->setCellValue('C3', 'Occupancy %');
        $s->setCellValue('F3', 'ARR (IDR)');
        $s->setCellValue('I3', 'Revenue (IDR)');
        $s->mergeCells('C3:E3');
        $s->mergeCells('F3:H3');
        $s->mergeCells('I3:K3');
        foreach (['A', 'B', 'C', 'F', 'I'] as $c) {
            $this->fillHeader($s, "{$c}3");
        }
        $sub = ['C' => 'Act', 'D' => 'Bud', 'E' => 'LY', 'F' => 'Act', 'G' => 'Bud', 'H' => 'LY', 'I' => 'Act', 'J' => 'Bud', 'K' => 'LY'];
        foreach ($sub as $col => $label) {
            $s->setCellValue("{$col}4", $label);
            $this->fillHeader($s, "{$col}4", self::SAND, self::INK);
        }
        $stats = $this->data->monthlyStats()->keyBy('month');
        $row = 5;
        foreach (ReportData::MONTHS as $i => $name) {
            $m = $stats->get($i + 1);
            $s->setCellValue("A{$row}", $name);
            $this->num($s, "B{$row}", $m?->rn_sold, '#,##0');
            $this->num($s, "C{$row}", $m?->occ_actual, self::PCT);
            $this->num($s, "D{$row}", $m?->occ_budget, self::PCT);
            $this->num($s, "E{$row}", $m?->occ_ly, self::PCT);
            $this->num($s, "F{$row}", $m?->arr_actual, self::MONEY);
            $this->num($s, "G{$row}", $m?->arr_budget, self::MONEY);
            $this->num($s, "H{$row}", $m?->arr_ly, self::MONEY);
            $this->num($s, "I{$row}", $m?->rev_actual, self::MONEY);
            $this->num($s, "J{$row}", $m?->rev_budget, self::MONEY);
            $this->num($s, "K{$row}", $m?->rev_ly, self::MONEY);
            $row++;
        }
        // Totals
        $t = $this->data->monthlyTotals();
        $s->setCellValue("A{$row}", 'Total');
        $this->num($s, "B{$row}", $t['rn_sold'], '#,##0');
        $this->num($s, "F{$row}", $t['arr_actual'], self::MONEY);
        $this->num($s, "G{$row}", $t['arr_budget'], self::MONEY);
        $this->num($s, "H{$row}", $t['arr_ly'], self::MONEY);
        $this->num($s, "I{$row}", $t['rev_actual'], self::MONEY);
        $this->num($s, "J{$row}", $t['rev_budget'], self::MONEY);
        $this->num($s, "K{$row}", $t['rev_ly'], self::MONEY);
        $s->getStyle("A{$row}:K{$row}")->getFont()->setBold(true);
        $s->getStyle("A{$row}:K{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $this->autosize($s, range('A', 'K'));
        $s->freezePane('A5');
    }

    /** Section C — grouped by category with per-group subtotals when available. */
    private function segmentSheet(): void
    {
        if (! $this->data->segmentsAreGrouped()) {
            $this->production('C · Weekly Production by Market Segment', 'C-Segment', $this->data->segments(), $this->data->segmentTotals(), 'Source / Segment');

            return;
        }

        $totals = $this->data->segmentTotals();
        $s = $this->sheet('C-Segment');
        $this->title($s, 'A1', 'C · Weekly Production by Market Segment');
        foreach (['A' => 'Source / Segment', 'B' => 'RN Sold', 'C' => 'Gross Revenue', 'D' => 'ARR', 'E' => '%'] as $c => $label) {
            $s->setCellValue("{$c}3", $label);
            $this->fillHeader($s, "{$c}3");
        }

        $pct = fn ($part) => ReportCalculator::sharePercent($part, $totals['rn']) !== null ? ReportCalculator::sharePercent($part, $totals['rn']) / 100 : null;
        $row = 4;
        foreach ($this->data->segmentGroups() as $group => $g) {
            $s->setCellValue("A{$row}", (string) $group);
            $s->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
            $row++;
            foreach ($g['rows'] as $r) {
                $s->setCellValue("A{$row}", '   '.$r->label);
                $this->num($s, "B{$row}", $r->rn_sold, '#,##0');
                $this->num($s, "C{$row}", $r->gross_revenue, self::MONEY);
                $this->num($s, "D{$row}", $r->arr(), self::MONEY);
                $this->num($s, "E{$row}", $pct($r->rn_sold), self::PCT);
                $row++;
            }
            $s->setCellValue("A{$row}", '   '.$group.' subtotal');
            $this->num($s, "B{$row}", $g['rn'], '#,##0');
            $this->num($s, "C{$row}", $g['revenue'], self::MONEY);
            $this->num($s, "E{$row}", $pct($g['rn']), self::PCT);
            $s->getStyle("A{$row}:E{$row}")->getFont()->setItalic(true);
            $row++;
        }

        $s->setCellValue("A{$row}", 'Total');
        $this->num($s, "B{$row}", $totals['rn'], '#,##0');
        $this->num($s, "C{$row}", $totals['revenue'], self::MONEY);
        $this->num($s, "D{$row}", $totals['arr'], self::MONEY);
        $this->num($s, "E{$row}", 1, self::PCT);
        $s->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $s->getStyle("A{$row}:E{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $this->autosize($s, ['A', 'B', 'C', 'D', 'E']);
    }

    private function production(string $title, string $tab, $rows, array $totals, string $labelHeading): void
    {
        $s = $this->sheet($tab);
        $this->title($s, 'A1', $title);
        foreach (['A' => $labelHeading, 'B' => 'RN Sold', 'C' => 'Gross Revenue', 'D' => 'ARR', 'E' => '%'] as $c => $label) {
            $s->setCellValue("{$c}3", $label);
            $this->fillHeader($s, "{$c}3");
        }
        $row = 4;
        foreach ($rows as $r) {
            $s->setCellValue("A{$row}", $r->label);
            $this->num($s, "B{$row}", $r->rn_sold, '#,##0');
            $this->num($s, "C{$row}", $r->gross_revenue, self::MONEY);
            $this->num($s, "D{$row}", $r->arr(), self::MONEY);
            $this->num($s, "E{$row}", ReportCalculator::sharePercent($r->rn_sold, $totals['rn']) !== null ? ReportCalculator::sharePercent($r->rn_sold, $totals['rn']) / 100 : null, self::PCT);
            $row++;
        }
        $s->setCellValue("A{$row}", 'Total');
        $this->num($s, "B{$row}", $totals['rn'], '#,##0');
        $this->num($s, "C{$row}", $totals['revenue'], self::MONEY);
        $this->num($s, "D{$row}", $totals['arr'], self::MONEY);
        $this->num($s, "E{$row}", 1, self::PCT);
        $s->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $s->getStyle("A{$row}:E{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $this->autosize($s, ['A', 'B', 'C', 'D', 'E']);
    }

    private function channels(): void
    {
        $s = $this->sheet('SM.4');
        $this->title($s, 'A1', 'E/F · Channel Inside (Room Nights)');
        $row = 3;
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $cols = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];
        foreach ($this->data->channelsByYear() as $year => $sources) {
            $s->setCellValue("A{$row}", (string) $year);
            $s->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::GOLD);
            $row++;
            $s->setCellValue("A{$row}", 'Source');
            foreach ($months as $i => $mn) {
                $s->setCellValue("{$cols[$i]}{$row}", $mn);
            }
            $s->setCellValue("O{$row}", 'YTD');
            $s->setCellValue("P{$row}", '%');
            $this->fillHeader($s, "A{$row}:P{$row}");
            $row++;
            $grand = (int) $sources->sum(fn ($x) => $x->ytd());
            foreach ($sources as $src) {
                $s->setCellValue("A{$row}", $src->source_label);
                foreach (ChannelMonthRn::MONTHS as $i => $mkey) {
                    $this->num($s, "{$cols[$i]}{$row}", $src->{$mkey}, '#,##0');
                }
                $this->num($s, "O{$row}", $src->ytd(), '#,##0');
                $pct = ReportCalculator::sharePercent($src->ytd(), $grand);
                $this->num($s, "P{$row}", $pct !== null ? $pct / 100 : null, self::PCT);
                $row++;
            }
            $s->setCellValue("A{$row}", 'Total');
            $this->num($s, "O{$row}", $grand, '#,##0');
            $s->getStyle("A{$row}:P{$row}")->getFont()->setBold(true);
            $row += 2;
        }
        $this->autosize($s, array_merge(['A'], $cols, ['O', 'P']));
    }

    private function activitiesSheet(): void
    {
        foreach ([['sales', 'SM.5', 'G · Sales Activity', 'Subject'], ['ecommerce', 'SM.6', 'G2 · E-commerce Activities', 'Task']] as [$dept, $tab, $title, $titleLabel]) {
            $s = $this->sheet($tab);
            $this->title($s, 'A1', $title);
            foreach (['A' => 'Date', 'B' => $titleLabel, 'C' => 'Notes / Remarks'] as $c => $label) {
                $s->setCellValue("{$c}3", $label);
                $this->fillHeader($s, "{$c}3");
            }
            $s->getColumnDimension('A')->setWidth(16);
            $s->getColumnDimension('B')->setWidth(30);
            $s->getColumnDimension('C')->setWidth(80);
            $row = 4;
            foreach ($this->data->activities($dept) as $a) {
                $s->setCellValue("A{$row}", $a->date_label ?? '');
                $s->setCellValue("B{$row}", $a->title ?? '');
                $s->setCellValue("C{$row}", $a->notes ?? '');
                $s->getStyle("C{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $row++;
            }
        }
    }

    private function social(): void
    {
        $s = $this->sheet('SM.7');
        $this->title($s, 'A1', 'H · Social Media Insight ('.$this->data->socialPlatform().')');
        foreach (['A' => 'Metric', 'B' => 'Last Week', 'C' => 'This Week', 'D' => 'Growth', 'E' => 'Growth %'] as $c => $label) {
            $s->setCellValue("{$c}3", $label);
            $this->fillHeader($s, "{$c}3");
        }
        $labels = SocialMedia::METRICS;
        $row = 4;
        foreach ($this->data->socialMetrics() as $m) {
            $s->setCellValue("A{$row}", $labels[$m->metric_key] ?? $m->metric_key);
            $this->num($s, "B{$row}", $m->last_week, '#,##0');
            $this->num($s, "C{$row}", $m->this_week, '#,##0');
            $this->num($s, "D{$row}", $m->growth(), '#,##0;[Red]-#,##0');
            $gp = $m->growthPercent();
            $this->num($s, "E{$row}", $gp !== null ? $gp / 100 : null, '0.0%;[Red]-0.0%');
            $row++;
        }
        $this->autosize($s, ['A', 'B', 'C', 'D', 'E']);
    }

    private function trainingsSheet(): void
    {
        $s = $this->sheet('SM.8');
        $this->title($s, 'A1', 'I · Training');
        foreach (['A' => 'Date', 'B' => 'Topic', 'C' => 'Duration', 'D' => 'Trainer', 'E' => 'Participants'] as $c => $label) {
            $s->setCellValue("{$c}3", $label);
            $this->fillHeader($s, "{$c}3");
        }
        $row = 4;
        foreach ($this->data->trainings() as $t) {
            $s->setCellValue("A{$row}", $t->date_label ?? '');
            $s->setCellValue("B{$row}", $t->topic);
            $s->setCellValue("C{$row}", $t->duration ?? '');
            $s->setCellValue("D{$row}", $t->trainer ?? '');
            $s->setCellValue("E{$row}", $t->participants ?? '');
            $row++;
        }
        $this->autosize($s, ['A', 'B', 'C', 'D', 'E']);
    }

    private function actionPlanSheet(): void
    {
        $s = $this->sheet('SM.9');
        $this->title($s, 'A1', 'J · Next Week Action Plan');
        foreach (['A' => 'Category', 'B' => 'Plan', 'C' => 'Start', 'D' => 'Deadline', 'E' => 'Remark'] as $c => $label) {
            $s->setCellValue("{$c}3", $label);
            $this->fillHeader($s, "{$c}3");
        }
        $s->getColumnDimension('E')->setWidth(70);
        $row = 4;
        foreach ($this->data->actionPlansByCategory() as $category => $plans) {
            foreach ($plans as $p) {
                $s->setCellValue("A{$row}", $category);
                $s->setCellValue("B{$row}", $p->plan);
                $s->setCellValue("C{$row}", $p->start_label ?? '');
                $s->setCellValue("D{$row}", $p->deadline_label ?? '');
                $s->setCellValue("E{$row}", $p->remark ?? '');
                $s->getStyle("E{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $row++;
            }
        }
    }

    private function ownerOverview(): void
    {
        $s = $this->sheet('Owner Overview');
        $this->title($s, 'A1', 'Owner Overview');
        // Repeater
        $s->setCellValue('A3', '1 · Repeater Guest — Room Performance');
        $s->getStyle('A3')->getFont()->setBold(true)->getColor()->setRGB(self::INK);
        foreach (['A' => 'Month', 'B' => 'Room Nights', 'C' => 'ADR', 'D' => 'Revenue'] as $c => $label) {
            $s->setCellValue("{$c}4", $label);
            $this->fillHeader($s, "{$c}4");
        }
        $row = 5;
        foreach ($this->data->ownerRepeater() as $m) {
            $s->setCellValue("A{$row}", $m->label);
            $this->num($s, "B{$row}", $m->room_nights, '#,##0');
            $this->num($s, "C{$row}", $m->adr(), self::MONEY);
            $this->num($s, "D{$row}", $m->revenue, self::MONEY);
            $row++;
        }
        $rt = $this->data->ownerRepeaterTotals();
        $s->setCellValue("A{$row}", 'Total');
        $this->num($s, "B{$row}", $rt['rn'], '#,##0');
        $this->num($s, "C{$row}", $rt['adr'], self::MONEY);
        $this->num($s, "D{$row}", $rt['revenue'], self::MONEY);
        $s->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $row += 3;
        // Mix
        $s->setCellValue("A{$row}", '2 · Channel Mix');
        $s->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB(self::INK);
        $row++;
        foreach (['A' => 'Source', 'B' => 'RN Sold', 'C' => 'ARR', 'D' => 'Revenue', 'E' => '%'] as $c => $label) {
            $s->setCellValue("{$c}{$row}", $label);
            $this->fillHeader($s, "{$c}{$row}");
        }
        $row++;
        $mt = $this->data->ownerMixTotals();
        foreach ($this->data->ownerMix() as $m) {
            $s->setCellValue("A{$row}", $m->label);
            $this->num($s, "B{$row}", $m->rn_sold, '#,##0');
            $this->num($s, "C{$row}", $m->arr(), self::MONEY);
            $this->num($s, "D{$row}", $m->gross_revenue, self::MONEY);
            $pct = ReportCalculator::sharePercent($m->rn_sold, $mt['rn']);
            $this->num($s, "E{$row}", $pct !== null ? $pct / 100 : null, self::PCT);
            $row++;
        }
        $this->autosize($s, ['A', 'B', 'C', 'D', 'E']);
    }

    // ---- helpers --------------------------------------------------------

    private function sheet(string $title): Worksheet
    {
        $s = $this->book->createSheet();
        $s->setTitle(mb_substr($title, 0, 31));

        return $s;
    }

    private function title(Worksheet $s, string $cell, string $text): void
    {
        $s->setCellValue($cell, $text);
        $s->getStyle($cell)->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::INK);
    }

    private function fillHeader(Worksheet $s, string $range, string $bg = self::INK, string $fg = 'FFFFFF'): void
    {
        $style = $s->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB($fg);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** Set a numeric cell with a format, or "–" when null. */
    private function num(Worksheet $s, string $cell, int|float|null $value, string $format): void
    {
        if ($value === null) {
            $s->setCellValueExplicit($cell, '–', DataType::TYPE_STRING);
            $s->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            return;
        }
        $s->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
        $s->getStyle($cell)->getNumberFormat()->setFormatCode($format);
        $s->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    private function autosize(Worksheet $s, array $cols): void
    {
        foreach ($cols as $c) {
            $s->getColumnDimension($c)->setAutoSize(true);
        }
    }
}
