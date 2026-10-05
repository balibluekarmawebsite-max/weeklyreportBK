<?php

namespace App\Livewire\Sections;

use App\Models\Activity;
use App\Models\ActivityLog;
use App\Models\ReportWeek;
use App\Services\Ai\AiException;
use App\Services\Ai\ReportNarrator;
use Livewire\Component;

/**
 * Sections G (Sales Activity), G2 (E-commerce) and Marketing logs.
 * A simple list of dated entries with a title and notes.
 */
class ActivityList extends Component
{
    public ReportWeek $week;

    public string $department = 'sales'; // sales | ecommerce | marketing

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $saved = false;

    public ?string $aiError = null;

    public function mount(ReportWeek $week, string $department = 'sales'): void
    {
        $this->week = $week;
        $this->department = $department;
        foreach ($week->activities()->where('department', $department)->orderBy('sort_order')->get() as $a) {
            $this->rows[] = ['id' => $a->id, 'date_label' => $a->date_label, 'title' => $a->title, 'notes' => $a->notes];
        }
        if (empty($this->rows)) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->rows[] = ['id' => null, 'date_label' => '', 'title' => '', 'notes' => ''];
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
            if (trim((string) $row['title']) === '' && trim((string) $row['notes']) === '') {
                continue;
            }
            $a = Activity::updateOrCreate(
                ['id' => $row['id'], 'report_week_id' => $this->week->id, 'department' => $this->department],
                [
                    'date_label' => $row['date_label'] ?: null,
                    'title' => $row['title'] ?: null,
                    'notes' => $row['notes'] ?: null,
                    'sort_order' => $order++,
                ],
            );
            $keep[] = $a->id;
        }
        $this->week->activities()->where('department', $this->department)->whereNotIn('id', $keep ?: [0])->delete();

        ActivityLog::record('updated', $this->week, 'Edited '.$this->heading());
        $this->saved = true;
        $this->rows = [];
        $this->mount($this->week, $this->department);
    }

    /** Rewrite / shorten / translate a row's free-text field with AI. */
    public function aiRewrite(int $i, string $field, string $mode, ReportNarrator $narrator): void
    {
        $this->aiError = null;
        if ($this->week->isLocked() || ! isset($this->rows[$i]) || ! in_array($field, ['title', 'notes'], true)) {
            return;
        }
        if (trim((string) ($this->rows[$i][$field] ?? '')) === '') {
            $this->aiError = 'Write some text first, then let AI rewrite it.';

            return;
        }

        try {
            $this->rows[$i][$field] = $narrator->rewrite($this->week, $this->department, $field, (string) $this->rows[$i][$field], $mode);
        } catch (AiException $e) {
            $this->aiError = $e->getMessage();
        }
    }

    public function heading(): string
    {
        return match ($this->department) {
            'ecommerce' => 'G2 · E-commerce Activities',
            'marketing' => 'Marketing Activities',
            default => 'G · Sales Activity',
        };
    }

    public function titleLabel(): string
    {
        return $this->department === 'ecommerce' ? 'Task' : 'Subject';
    }

    public function render()
    {
        return view('livewire.sections.activity-list', [
            'aiReady' => app(ReportNarrator::class)->enabled(),
        ]);
    }
}
