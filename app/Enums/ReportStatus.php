<?php

namespace App\Enums;

/**
 * Lifecycle of a weekly report.
 * Draft -> In progress -> Ready for review -> Approved (locked) -> Exported.
 */
enum ReportStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case ReadyForReview = 'ready_for_review';
    case Approved = 'approved';
    case Exported = 'exported';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InProgress => 'In progress',
            self::ReadyForReview => 'Ready for review',
            self::Approved => 'Approved',
            self::Exported => 'Exported',
        };
    }

    /** Tailwind colour token used for the status badge. */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::InProgress => 'amber',
            self::ReadyForReview => 'sky',
            self::Approved => 'emerald',
            self::Exported => 'teal',
        };
    }

    /** A locked report can no longer be edited (only re-exported). */
    public function isLocked(): bool
    {
        return in_array($this, [self::Approved, self::Exported], true);
    }
}
