<?php

namespace App\Livewire\Sections;

use App\Models\ActionPlan as ActionPlanModel;
use App\Models\ActivityLog;
use App\Models\ReportWeek;
use App\Services\Ai\AiException;
use App\Services\Ai\ReportNarrator;
use Livewire\Component;

/** Section J — Next Week Action Plan (grouped by category). */
class ActionPlan extends Component
{
    public ReportWeek $week;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $saved = false;

    public ?string $aiError = null;

    public function mount(ReportWeek $week): void
    {
        $this->week = $week;
        foreach ($week->actionPlans()->get() as $p) {
            $this->rows[] = [
                'id' => $p->id, 'category' => $p->category, 'plan' => $p->plan,
                'start_label' => $p->start_label, 'deadline_label' => $p->deadline_label, 'remark' => $p->remark,
            ];
        }
        if (empty($this->rows)) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->rows[] = ['id' => null, 'category' => '', 'plan' => '', 'start_label' => '', 'deadline_label' => '', 'remark' => ''];
    }

    public function removeRow(int $i): void
    {
        unset($this->rows[$i]);
        $this->rows = array_values($this->rows);
    }

    public function save(): void
    {
        if ($this->week->isLocked()) {
            return;
        }

        $keep = [];
        $order = 0;
        foreach ($this->rows as $row) {
            if (trim((string) $row['plan']) === '') {
                continue;
            }
            $p = ActionPlanModel::updateOrCreate(
                ['id' => $row['id'], 'report_week_id' => $this->week->id],
                [
                    'category' => $row['category'] ?: null,
                    'plan' => $row['plan'],
                    'start_label' => $row['start_label'] ?: null,
                    'deadline_label' => $row['deadline_label'] ?: null,
                    'remark' => $row['remark'] ?: null,
                    'sort_order' => $order++,
                ],
            );
            $keep[] = $p->id;
        }
        $this->week->actionPlans()->whereNotIn('id', $keep ?: [0])->delete();

        ActivityLog::record('updated', $this->week, 'Edited Section J (Action Plan)');
        $this->saved = true;
        $this->rows = [];
        $this->mount($this->week);
    }

    /** Rewrite / shorten / translate a row's free-text field with AI. */
    public function aiRewrite(int $i, string $field, string $mode, ReportNarrator $narrator): void
    {
        $this->aiError = null;
        if ($this->week->isLocked() || ! isset($this->rows[$i]) || ! in_array($field, ['plan', 'remark'], true)) {
            return;
        }
        if (trim((string) ($this->rows[$i][$field] ?? '')) === '') {
            $this->aiError = 'Write some text first, then let AI rewrite it.';

            return;
        }

        try {
            $this->rows[$i][$field] = $narrator->rewrite($this->week, 'action_plan', $field, (string) $this->rows[$i][$field], $mode);
        } catch (AiException $e) {
            $this->aiError = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.sections.action-plan', [
            'aiReady' => app(ReportNarrator::class)->enabled(),
        ]);
    }
}
