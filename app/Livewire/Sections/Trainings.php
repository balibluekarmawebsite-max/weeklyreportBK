<?php

namespace App\Livewire\Sections;

use App\Models\ActivityLog;
use App\Models\ReportWeek;
use App\Models\Training;
use Livewire\Component;

/** Section I — Training. */
class Trainings extends Component
{
    public ReportWeek $week;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $saved = false;

    public function mount(ReportWeek $week): void
    {
        $this->week = $week;
        foreach ($week->trainings()->get() as $t) {
            $this->rows[] = [
                'id' => $t->id, 'date_label' => $t->date_label, 'topic' => $t->topic,
                'duration' => $t->duration, 'trainer' => $t->trainer, 'participants' => $t->participants,
            ];
        }
        if (empty($this->rows)) {
            $this->addRow();
        }
    }

    public function addRow(): void
    {
        $this->rows[] = ['id' => null, 'date_label' => '', 'topic' => '', 'duration' => '', 'trainer' => '', 'participants' => ''];
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
            if (trim((string) $row['topic']) === '') {
                continue;
            }
            $t = Training::updateOrCreate(
                ['id' => $row['id'], 'report_week_id' => $this->week->id],
                [
                    'date_label' => $row['date_label'] ?: null,
                    'topic' => $row['topic'],
                    'duration' => $row['duration'] ?: null,
                    'trainer' => $row['trainer'] ?: null,
                    'participants' => $row['participants'] ?: null,
                    'sort_order' => $order++,
                ],
            );
            $keep[] = $t->id;
        }
        $this->week->trainings()->whereNotIn('id', $keep ?: [0])->delete();

        ActivityLog::record('updated', $this->week, 'Edited Section I (Training)');
        $this->saved = true;
        $this->rows = [];
        $this->mount($this->week);
    }

    public function render()
    {
        return view('livewire.sections.trainings');
    }
}
