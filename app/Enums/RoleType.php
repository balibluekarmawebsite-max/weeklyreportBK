<?php

namespace App\Enums;

/**
 * Application roles. See docs/PLAN.md section 7.
 *   Admin        - everything, approve & lock (DoS / Revenue manager).
 *   Editor       - edit all sections (marketing lead).
 *   Contributor  - only their own department section(s).
 *   Viewer       - read & download only (GM / Owner).
 */
enum RoleType: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Contributor = 'contributor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Editor => 'Editor',
            self::Contributor => 'Department contributor',
            self::Viewer => 'Viewer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access; can approve and lock reports.',
            self::Editor => 'Can edit all sections of any report.',
            self::Contributor => 'Can edit only their own department sections.',
            self::Viewer => 'Read-only; can view and download reports.',
        };
    }
}
