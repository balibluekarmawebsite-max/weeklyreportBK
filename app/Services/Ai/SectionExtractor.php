<?php

namespace App\Services\Ai;

use App\Livewire\Sections\SocialMedia;
use App\Models\ActivityLog;
use App\Models\AiDraft;
use App\Models\ReportWeek;
use App\Support\ReportSections;

/**
 * Uses the AI to read a report section's data out of an uploaded file (CSV/text)
 * or image/screenshot into structured rows matching that section's columns.
 *
 * The model is told to use ONLY values present in the input and never to invent
 * numbers; the result is always shown back to the user as an editable draft
 * before anything is saved (the UI enforces review — see SectionImport).
 */
class SectionExtractor
{
    public function __construct(private GroqClient $groq) {}

    public function enabled(): bool
    {
        return $this->groq->configured();
    }

    /**
     * @param  array<int, string>  $imagePaths  absolute paths to image files
     * @return array<int, array<string, mixed>> rows keyed by the section's column fields
     */
    public function extract(string $sectionKey, ReportWeek $week, ?string $csvText = null, array $imagePaths = []): array
    {
        $section = ReportSections::get($sectionKey);
        if (! $section) {
            throw new \InvalidArgumentException("Unknown section [{$sectionKey}].");
        }
        $columns = $section['columns'];

        $raw = $this->groq->chat(
            $this->messages($section, $columns, $sectionKey, $csvText, $imagePaths),
            array_merge(
                ['temperature' => 0, 'response_format' => ['type' => 'json_object']],
                $imagePaths ? ['model' => $this->groq->visionModel()] : [],
            ),
        );

        $rows = $this->rowsFromJson($raw);
        $out = [];
        foreach ($rows as $r) {
            if (is_array($r)) {
                $out[] = $this->normalizeRow($r, $columns);
            }
        }

        $this->record($week, $sectionKey, $imagePaths ? 'extract_image' : 'extract_csv', count($out));

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function messages(array $section, array $columns, string $sectionKey, ?string $csvText, array $imagePaths): array
    {
        $colSpec = collect($columns)
            ->map(fn ($c, $k) => "- \"{$k}\" ({$c['type']}): {$c['label']}")
            ->implode("\n");

        $system = implode("\n", array_filter([
            'You extract tabular data from a hotel weekly report into JSON.',
            'Return ONLY a JSON object of the form {"rows": [ {..}, {..} ]}.',
            'Each row object must use exactly these fields:',
            $colSpec,
            'Rules: use ONLY values that actually appear in the provided data or image. '
                .'Never invent, guess, or estimate a number. If a value is missing, use null. '
                .'For "percent" fields return a fraction between 0 and 1 (73.5% → 0.735). '
                .'For "money" and "int" fields return a plain number with no thousands separators or currency symbols. '
                .'Produce one row per record.',
            $sectionKey === 'social' ? 'For the "metric" field use one of: '.implode(', ', array_values(SocialMedia::METRICS)).'.' : null,
        ]));

        $userText = $section['label']."\n".$section['hint']."\n\n".
            ($csvText !== null && $csvText !== ''
                ? "DATA:\n".mb_substr($csvText, 0, 30000)
                : 'Read the data from the attached image(s).');

        $userContent = $userText;
        if ($imagePaths) {
            $parts = [['type' => 'text', 'text' => $userText]];
            foreach ($imagePaths as $path) {
                $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $this->dataUrl($path)]];
            }
            $userContent = $parts;
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userContent],
        ];
    }

    private function dataUrl(string $path): string
    {
        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }

    /** @return array<int, mixed> */
    private function rowsFromJson(string $raw): array
    {
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            // Lenient: pull the first {...} block out of a chatty reply.
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $json = json_decode($m[0], true);
            }
        }
        if (! is_array($json)) {
            throw AiException::emptyResponse();
        }

        if (isset($json['rows']) && is_array($json['rows'])) {
            return $json['rows'];
        }

        // A bare array of rows, or a single row object.
        return array_is_list($json) ? $json : [$json];
    }

    /** @return array<string, mixed> */
    private function normalizeRow(array $row, array $columns): array
    {
        $out = [];
        foreach ($columns as $field => $spec) {
            $out[$field] = $this->coerce($row[$field] ?? null, $spec['type']);
        }

        return $out;
    }

    private function coerce(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return $type === 'text' ? '' : null;
        }
        if ($type === 'text') {
            return trim((string) $value);
        }
        // numeric types: strip separators / symbols.
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
        if ($clean === '' || $clean === '-' || $clean === '.') {
            return null;
        }

        return match ($type) {
            'int' => (int) round((float) $clean),
            default => (float) $clean, // money, percent
        };
    }

    private function record(ReportWeek $week, string $sectionKey, string $mode, int $rowCount): void
    {
        $draft = AiDraft::create([
            'report_week_id' => $week->id,
            'section' => $sectionKey,
            'field_key' => null,
            'mode' => $mode,
            'model' => $mode === 'extract_image' ? $this->groq->visionModel() : $this->groq->model(),
            'output' => "Extracted {$rowCount} row(s) for section {$sectionKey} (draft — pending review).",
            'status' => 'draft',
            'user_id' => auth()->id(),
        ]);

        ActivityLog::record('ai_generated', $draft, "AI extracted {$rowCount} row(s) for section {$sectionKey}", ['section' => $sectionKey, 'mode' => $mode]);
    }
}
