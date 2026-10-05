<?php

namespace App\Services\Ai;

use App\Models\ActivityLog;
use App\Models\AiDraft;
use App\Models\ReportWeek;
use App\Services\ReportData;
use App\Support\Format;
use App\Support\ReportCalculator;

/**
 * Turns a report week's calculated figures into draft commentary and rewrites
 * department notes — grounded strictly on the numbers in {@see ReportData}.
 *
 * The guiding rule (docs/PLAN.md section 5): the AI never invents numbers. It
 * is handed a FACTS block of already-formatted figures and told to quote only
 * those; everything it produces is marked an "AI draft" until a person edits
 * and saves it.
 */
class ReportNarrator
{
    public function __construct(private GroqClient $groq) {}

    /** Per-block drafting instruction for Section A (keyed by block key). */
    private const BLOCK_INSTRUCTIONS = [
        'financial' => 'Summarise the financial performance for the headline month: occupancy, ADR and room revenue versus budget and last year. State clearly whether each is above or below budget and by how much.',
        'market' => 'Give a brief market overview using the market-segment mix and channel distribution in the FACTS (which segments and booking sources lead). Do not invent country or competitor data that is not in the FACTS.',
        'pace' => 'Comment on booking pace using the actual-versus-budget occupancy and revenue figures in the FACTS. If forward on-the-books pace is not provided, say the forward pace detail is to be added rather than inventing it.',
        'countries' => 'If no country-level data is present in the FACTS, write a one-line lead-in and leave a bracketed note like [add top source countries]. Do not invent country names or percentages.',
        'booking_window' => 'If booking-window / lead-time data is not present in the FACTS, write a one-line lead-in and leave [add booking window / lead time]. Do not invent figures.',
        'booking_ranking' => 'If Booking.com ranking or review-score data is not present in the FACTS, write a one-line lead-in and leave [add Booking.com ranking & review score]. Do not invent a rank or score.',
        'learning' => 'Summarise the training / learning activity for the week from the trainings listed in the FACTS. If none are listed, state that no training was recorded this week.',
    ];

    /** Rewrite-mode instructions for free-text fields. */
    private const REWRITE_INSTRUCTIONS = [
        'rewrite' => 'Rewrite the text below to be clearer and more professional. Keep every fact and number exactly as written. Return only the rewritten text.',
        'shorten' => 'Shorten the text below to its essential point in one or two sentences. Keep every fact and number exactly as written. Return only the shortened text.',
        'translate_id' => 'Translate the text below into Indonesian (Bahasa Indonesia). Keep every number and figure unchanged. Return only the translation.',
        'translate_en' => 'Translate the text below into English. Keep every number and figure unchanged. Return only the translation.',
    ];

    /** AI features are usable (an API key is configured). */
    public function enabled(): bool
    {
        return $this->groq->configured();
    }

    /** Draft the body for one Section A block, grounded on the week's figures. */
    public function draftBlock(ReportWeek $week, string $key, string $heading): string
    {
        $data = new ReportData($week);
        $instruction = self::BLOCK_INSTRUCTIONS[$key] ?? 'Write a short, professional paragraph for this section using only the FACTS.';

        $text = $this->groq->chat([
            ['role' => 'system', 'content' => $this->systemPrompt($data)],
            ['role' => 'user', 'content' => implode("\n", [
                "Section: {$heading}",
                'Report period: '.($week->label ?? ''),
                '',
                'FACTS (the only figures you may cite):',
                $this->facts($data),
                '',
                "Task: {$instruction}",
                '',
                'Write 2–4 sentences of plain prose. No markdown headings, no bullet characters.',
            ])],
        ]);

        $this->record($week, 'overview', $key, 'draft', $text);

        return $text;
    }

    /**
     * Rewrite / shorten / translate a free-text field, preserving all figures.
     *
     * @param  string  $mode  rewrite | shorten | translate_id | translate_en
     */
    public function rewrite(ReportWeek $week, string $section, ?string $field, string $text, string $mode): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $instruction = self::REWRITE_INSTRUCTIONS[$mode] ?? self::REWRITE_INSTRUCTIONS['rewrite'];

        $out = $this->groq->chat([
            ['role' => 'system', 'content' => 'You are an editor for a hotel Sales & Marketing weekly report. Preserve every number and fact exactly; never add new facts. Return only the requested text with no preamble or quotation marks.'],
            ['role' => 'user', 'content' => $instruction."\n\n---\n".$text],
        ]);

        $this->record($week, $section, $field, $mode, $out);

        return $out;
    }

    // ---- prompt building -------------------------------------------------

    private function systemPrompt(ReportData $data): string
    {
        $p = $data->property();

        return implode(' ', [
            'You are a senior hotel Sales & Marketing analyst writing the weekly report for '.($p?->name ?? 'the hotel').'.',
            'Write in professional English suitable for hotel ownership.',
            'Use ONLY the figures in the FACTS block; never invent, estimate, or re-round them.',
            'If a figure you would need is not in the FACTS, write around it or leave a short bracketed note like [add detail] — do not fabricate numbers, country names, rankings, or competitor data.',
            'Quote figures exactly as they are formatted in the FACTS.',
        ]);
    }

    /** The grounded facts block — already-formatted figures, null-safe. */
    public function facts(ReportData $data): string
    {
        $pct = fn ($f) => $f === null ? Format::EMPTY : Format::percent($f * 100);
        $lines = [];

        $p = $data->property();
        $lines[] = 'Property: '.($p?->name ?? '–').($p?->rooms_count ? ', '.$p->rooms_count.' rooms' : '').'.';

        $stat = $data->currentStat();
        $month = $data->currentMonth();
        if ($stat && $month) {
            $name = ReportData::MONTHS[$month - 1];
            $occVar = $this->delta($stat->occ_actual, $stat->occ_budget, false, true);
            $adrVar = $this->delta($stat->arr_actual, $stat->arr_budget, true);
            $revVar = $this->delta($stat->rev_actual, $stat->rev_budget, true);
            $lines[] = "Headline month: {$name}.";
            $lines[] = "- Occupancy: actual {$pct($stat->occ_actual)}, budget {$pct($stat->occ_budget)}, last year {$pct($stat->occ_ly)}{$occVar}.";
            $lines[] = '- ADR: actual '.Format::idrPrefixed($stat->arr_actual).', budget '.Format::idrPrefixed($stat->arr_budget).', last year '.Format::idrPrefixed($stat->arr_ly).$adrVar.'.';
            $lines[] = '- Room revenue: actual '.Format::idrPrefixed($stat->rev_actual).', budget '.Format::idrPrefixed($stat->rev_budget).', last year '.Format::idrPrefixed($stat->rev_ly).$revVar.'.';
            $lines[] = '- Room nights sold: '.Format::number($stat->rn_sold).'.';
        } else {
            $lines[] = 'Headline month: no monthly figures entered yet.';
        }

        $totals = $data->monthlyTotals();
        $lines[] = 'Year-to-date: '.Format::number($totals['rn_sold']).' room nights, revenue '.Format::idrPrefixed($totals['rev_actual']).'.';

        $segTot = $data->segmentTotals();
        $topSeg = $data->segments()->sortByDesc('rn_sold')->take(5);
        if ($topSeg->isNotEmpty()) {
            $lines[] = 'Top market segments this week (room nights, share of '.Format::number($segTot['rn']).'):';
            foreach ($topSeg as $s) {
                $lines[] = '- '.$s->label.': '.Format::number($s->rn_sold).' RN ('.Format::percent(ReportCalculator::sharePercent($s->rn_sold, $segTot['rn'])).'), revenue '.Format::idrPrefixed($s->gross_revenue).'.';
            }
        }

        $byYear = $data->channelsByYear();
        if ($byYear->isNotEmpty()) {
            $year = $byYear->keys()->max();
            $sources = $byYear->get($year)->sortByDesc(fn ($s) => $s->ytd())->take(5);
            if ($sources->isNotEmpty()) {
                $lines[] = "Leading booking channels ({$year}, room nights YTD):";
                foreach ($sources as $src) {
                    $lines[] = '- '.$src->source_label.': '.Format::number($src->ytd()).' RN.';
                }
            }
        }

        $trainings = $data->trainings();
        $lines[] = 'Trainings recorded this week: '.$trainings->count().
            ($trainings->isNotEmpty() ? ' ('.$trainings->pluck('topic')->filter()->take(5)->implode('; ').')' : '').'.';

        return implode("\n", $lines);
    }

    /** A short " (X below/above budget)" clause, or '' when not computable. */
    private function delta(int|float|null $actual, int|float|null $compare, bool $money, bool $points = false): string
    {
        if ($points) {
            if ($actual === null || $compare === null) {
                return '';
            }
            $pts = ($actual - $compare) * 100;

            return abs($pts) < 0.05 ? ' (on budget)' : sprintf(' (%.1f pts %s budget)', abs($pts), $pts < 0 ? 'below' : 'above');
        }

        $var = Format::variance($actual, $compare);
        if ($var['pct'] === null) {
            return '';
        }

        return abs($var['pct']) < 0.05 ? ' (on budget)' : sprintf(' (%.1f%% %s budget)', abs($var['pct']), $var['pct'] < 0 ? 'below' : 'above');
    }

    // ---- audit -----------------------------------------------------------

    private function record(ReportWeek $week, string $section, ?string $field, string $mode, string $output): AiDraft
    {
        $draft = AiDraft::create([
            'report_week_id' => $week->id,
            'section' => $section,
            'field_key' => $field,
            'mode' => $mode,
            'model' => $this->groq->model(),
            'output' => $output,
            'status' => 'draft',
            'user_id' => auth()->id(),
        ]);

        ActivityLog::record(
            'ai_generated',
            $draft,
            ucfirst(str_replace('_', ' ', $mode)).' — '.$section.($field ? " ({$field})" : ''),
            ['model' => $this->groq->model(), 'section' => $section, 'field' => $field],
        );

        return $draft;
    }
}
