<?php

namespace App\Enums;

/**
 * Departments that contribute to the weekly report.
 * A Contributor user is tied to one department and edits only its sections.
 */
enum Department: string
{
    case Sales = 'sales';
    case Ecommerce = 'ecommerce';
    case SocialMedia = 'social_media';
    case GraphicDesign = 'graphic_design';
    case DigitalMarketing = 'digital_marketing';
    case Training = 'training';
    case Revenue = 'revenue';

    public function label(): string
    {
        return match ($this) {
            self::Sales => 'Sales',
            self::Ecommerce => 'E-commerce',
            self::SocialMedia => 'Social Media',
            self::GraphicDesign => 'Graphic Design',
            self::DigitalMarketing => 'Digital Marketing',
            self::Training => 'Training',
            self::Revenue => 'Revenue',
        };
    }

    /**
     * Report sections (see docs/PLAN.md section 1) this department owns.
     */
    public function sections(): array
    {
        return match ($this) {
            self::Sales => ['G'],
            self::Ecommerce => ['G2'],
            self::SocialMedia => ['H'],
            self::GraphicDesign => ['H'],
            self::DigitalMarketing => ['Marketing activities'],
            self::Training => ['I'],
            self::Revenue => ['B', 'C', 'D', 'E/F', 'Owner Overview'],
        };
    }
}
