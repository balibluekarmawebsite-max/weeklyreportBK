<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\MarketSegment;
use App\Models\RateCode;
use Illuminate\Database\Seeder;

/**
 * Seeds the global default lists (property_id = null) used across reports:
 * channels (E/F), market segments (C), rate codes (D).
 * These can be edited per-property later in Settings.
 */
class ListsSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['code' => 'BOOKING', 'name' => 'Booking.com'],
            ['code' => 'EXPEDIA', 'name' => 'Expedia'],
            ['code' => 'AGODA', 'name' => 'Agoda'],
            ['code' => 'TRAVELOKA', 'name' => 'Traveloka'],
            ['code' => 'WEB-ALARIC', 'name' => 'Website (ALARIC)'],
            ['code' => 'DIRECT', 'name' => 'Direct / Reservation'],
            ['code' => 'TA-WHOLESALE', 'name' => 'Travel Agent / Wholesale'],
            ['code' => 'WALK-IN', 'name' => 'Walk-in'],
            ['code' => 'OTHER', 'name' => 'Other'],
        ];
        foreach ($channels as $i => $c) {
            Channel::updateOrCreate(
                ['property_id' => null, 'code' => $c['code']],
                ['name' => $c['name'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        $segments = [
            ['code' => 'OTA', 'name' => 'Online Travel Agent'],
            ['code' => 'FIT', 'name' => 'FIT / Individual'],
            ['code' => 'CORP', 'name' => 'Corporate'],
            ['code' => 'GOV', 'name' => 'Government'],
            ['code' => 'TA', 'name' => 'Travel Agent'],
            ['code' => 'WHOLESALE', 'name' => 'Wholesale'],
            ['code' => 'LONGSTAY', 'name' => 'Long Stay'],
            ['code' => 'COMP', 'name' => 'Complimentary'],
            ['code' => 'HOUSE', 'name' => 'House Use'],
        ];
        foreach ($segments as $i => $s) {
            MarketSegment::updateOrCreate(
                ['property_id' => null, 'code' => $s['code']],
                ['name' => $s['name'], 'sort_order' => $i, 'is_active' => true],
            );
        }

        $rateCodes = [
            ['code' => 'BAR', 'name' => 'Best Available Rate', 'category' => 'BAR'],
            ['code' => 'PROMO', 'name' => 'Promotion', 'category' => 'Promotion'],
            ['code' => 'PKG', 'name' => 'Package', 'category' => 'Package'],
            ['code' => 'CORP', 'name' => 'Corporate Rate', 'category' => 'Corporate'],
            ['code' => 'LONGSTAY', 'name' => 'Long Stay Rate', 'category' => 'Promotion'],
            ['code' => 'COMP', 'name' => 'Complimentary', 'category' => 'Other'],
        ];
        foreach ($rateCodes as $i => $r) {
            RateCode::updateOrCreate(
                ['property_id' => null, 'code' => $r['code']],
                ['name' => $r['name'], 'category' => $r['category'], 'sort_order' => $i, 'is_active' => true],
            );
        }
    }
}
