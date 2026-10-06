@php
    use App\Support\Format;
    use App\Support\ReportCalculator;
    use App\Models\ChannelMonthRn;
    $p = $d->property();
    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $pct = fn($f) => $f === null ? '–' : number_format($f * 100, 1).'%';
@endphp
<style>
    body { font-family: dejavusans, sans-serif; color: #0A2B3A; font-size: 9pt; }
    h1.cover { font-size: 30pt; color: #15607F; margin: 0; }
    h2 { font-size: 14pt; color: #15607F; border-bottom: 2px solid #C9A24B; padding-bottom: 3px; margin: 0 0 8px; }
    h3 { font-size: 10.5pt; color: #15607F; margin: 10px 0 3px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th { background: #15607F; color: #fff; font-size: 8pt; padding: 4px 5px; text-align: right; }
    th.l, td.l { text-align: left; }
    td { padding: 3px 5px; font-size: 8.5pt; text-align: right; border-bottom: 0.4px solid #E9E2D2; }
    tr:nth-child(even) td { background: #FAF8F3; }
    tr.total td { font-weight: bold; border-top: 1px solid #15607F; background: #F4F0E7; }
    .muted { color: #888; }
    .note { font-size: 8pt; color: #444; white-space: pre-wrap; }
    .cat { font-weight: bold; color: #206E8F; background:#F4F0E7; }
</style>

{{-- COVER --}}
<div style="text-align:center; padding-top:180px;">
    <div style="color:#C9A24B; font-weight:bold; letter-spacing:3px; font-size:11pt;">WEEKLY REPORT</div>
    <h1 class="cover" style="margin-top:12px;">{{ $p?->name }}</h1>
    <div style="margin-top:14px; font-size:13pt; color:#206E8F;">Sales &amp; Marketing</div>
    <div style="margin-top:6px; font-size:12pt;">{{ $d->week->label }}</div>
    <div style="margin-top:4px;" class="muted">Status: {{ $d->week->status->label() }}</div>
</div>
<pagebreak />

{{-- A. OVERVIEW --}}
<h2>A · Sales &amp; Marketing Overview</h2>
@foreach($d->overviewBlocks() as $b)
    <h3>{{ $b->heading }}</h3>
    <div class="note">{{ $b->body ?: '–' }}</div>
@endforeach
<pagebreak />

{{-- B. YTD --}}
<h2>B · Year to Date Actual &amp; On-Hand Forecast</h2>
@php $stats = $d->monthlyStats()->keyBy('month'); $t = $d->monthlyTotals(); @endphp
<table>
    <thead>
        <tr>
            <th class="l" rowspan="2">Month</th><th rowspan="2">RN Sold</th>
            <th colspan="3">Occupancy %</th><th colspan="3">ARR (IDR)</th><th colspan="3">Revenue (IDR)</th>
        </tr>
        <tr><th>Act</th><th>Bud</th><th>LY</th><th>Act</th><th>Bud</th><th>LY</th><th>Act</th><th>Bud</th><th>LY</th></tr>
    </thead>
    <tbody>
    @foreach($months as $i => $name)
        @php $m = $stats->get($i+1); @endphp
        <tr>
            <td class="l">{{ $name }}</td>
            <td>{{ Format::number($m?->rn_sold) }}</td>
            <td>{{ $pct($m?->occ_actual) }}</td><td>{{ $pct($m?->occ_budget) }}</td><td>{{ $pct($m?->occ_ly) }}</td>
            <td>{{ Format::idr($m?->arr_actual) }}</td><td>{{ Format::idr($m?->arr_budget) }}</td><td>{{ Format::idr($m?->arr_ly) }}</td>
            <td>{{ Format::idr($m?->rev_actual) }}</td><td>{{ Format::idr($m?->rev_budget) }}</td><td>{{ Format::idr($m?->rev_ly) }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td class="l">Total</td><td>{{ Format::number($t['rn_sold']) }}</td>
            <td></td><td></td><td></td>
            <td>{{ Format::idr($t['arr_actual']) }}</td><td>{{ Format::idr($t['arr_budget']) }}</td><td>{{ Format::idr($t['arr_ly']) }}</td>
            <td>{{ Format::idr($t['rev_actual']) }}</td><td>{{ Format::idr($t['rev_budget']) }}</td><td>{{ Format::idr($t['rev_ly']) }}</td>
        </tr>
    </tbody>
</table>

{{-- C — Weekly Production by Market Segment (grouped by category when available) --}}
@php $segTot = $d->segmentTotals(); @endphp
<h2 style="margin-top:14px;">C · Weekly Production by Market Segment</h2>
<table>
    <thead><tr><th class="l">Source / Segment</th><th>RN Sold</th><th>Gross Revenue</th><th>ARR</th><th>%</th></tr></thead>
    <tbody>
    @if($d->segmentsAreGrouped())
        @foreach($d->segmentGroups() as $group => $g)
            <tr class="total"><td class="l" colspan="5">{{ $group }}</td></tr>
            @foreach($g['rows'] as $r)
                <tr>
                    <td class="l" style="padding-left:14px;">{{ $r->label }}</td>
                    <td>{{ Format::number($r->rn_sold) }}</td>
                    <td>{{ Format::idr($r->gross_revenue) }}</td>
                    <td>{{ Format::idr($r->arr()) }}</td>
                    <td>{{ Format::percent(ReportCalculator::sharePercent($r->rn_sold, $segTot['rn'])) }}</td>
                </tr>
            @endforeach
            <tr><td class="l" style="padding-left:14px;font-style:italic;">{{ $group }} subtotal</td><td>{{ Format::number($g['rn']) }}</td><td>{{ Format::idr($g['revenue']) }}</td><td></td><td>{{ Format::percent(ReportCalculator::sharePercent($g['rn'], $segTot['rn'])) }}</td></tr>
        @endforeach
    @else
        @foreach($d->segments() as $r)
            <tr>
                <td class="l">{{ $r->label }}</td>
                <td>{{ Format::number($r->rn_sold) }}</td>
                <td>{{ Format::idr($r->gross_revenue) }}</td>
                <td>{{ Format::idr($r->arr()) }}</td>
                <td>{{ Format::percent(ReportCalculator::sharePercent($r->rn_sold, $segTot['rn'])) }}</td>
            </tr>
        @endforeach
    @endif
        <tr class="total"><td class="l">Total</td><td>{{ Format::number($segTot['rn']) }}</td><td>{{ Format::idr($segTot['revenue']) }}</td><td>{{ Format::idr($segTot['arr']) }}</td><td>100%</td></tr>
    </tbody>
</table>

{{-- D — Rate Code / Promotion --}}
@php $rcTot = $d->rateCodeTotals(); @endphp
<h2 style="margin-top:14px;">D · Rate Code / Promotion</h2>
<table>
    <thead><tr><th class="l">Promotion</th><th>RN Sold</th><th>Gross Revenue</th><th>ARR</th><th>%</th></tr></thead>
    <tbody>
    @foreach($d->rateCodes() as $r)
        <tr>
            <td class="l">{{ $r->label }}</td>
            <td>{{ Format::number($r->rn_sold) }}</td>
            <td>{{ Format::idr($r->gross_revenue) }}</td>
            <td>{{ Format::idr($r->arr()) }}</td>
            <td>{{ Format::percent(ReportCalculator::sharePercent($r->rn_sold, $rcTot['rn'])) }}</td>
        </tr>
    @endforeach
        <tr class="total"><td class="l">Total</td><td>{{ Format::number($rcTot['rn']) }}</td><td>{{ Format::idr($rcTot['revenue']) }}</td><td>{{ Format::idr($rcTot['arr']) }}</td><td>100%</td></tr>
    </tbody>
</table>
<pagebreak />

{{-- E/F Channels (current year) --}}
<h2>E/F · Channel Inside (Room Nights)</h2>
@php $mcols = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']; @endphp
@foreach($d->channelsByYear()->sortKeysDesc()->take(2) as $year => $sources)
    @php $grand = (int) $sources->sum(fn($x)=>$x->ytd()); @endphp
    <h3>{{ $year }}</h3>
    <table>
        <thead><tr><th class="l">Source</th>@foreach($mcols as $mn)<th>{{ $mn }}</th>@endforeach<th>YTD</th><th>%</th></tr></thead>
        <tbody>
        @foreach($sources as $src)
            <tr>
                <td class="l">{{ $src->source_label }}</td>
                @foreach(ChannelMonthRn::MONTHS as $mk)<td>{{ $src->{$mk} ?: '' }}</td>@endforeach
                <td>{{ Format::number($src->ytd()) }}</td>
                <td>{{ Format::percent(ReportCalculator::sharePercent($src->ytd(), $grand)) }}</td>
            </tr>
        @endforeach
            <tr class="total"><td class="l">Total</td>@foreach($mcols as $mn)<td></td>@endforeach<td>{{ Format::number($grand) }}</td><td>100%</td></tr>
        </tbody>
    </table>
@endforeach
<pagebreak />

{{-- G & G2 --}}
@foreach([['G · Sales Activity', $d->activities('sales'), 'Subject'], ['G2 · E-commerce Activities', $d->activities('ecommerce'), 'Task']] as [$title,$acts,$lh])
    <h2 style="margin-top:12px;">{{ $title }}</h2>
    <table>
        <thead><tr><th class="l" style="width:14%;">Date</th><th class="l" style="width:24%;">{{ $lh }}</th><th class="l">Notes / Remarks</th></tr></thead>
        <tbody>
        @forelse($acts as $a)
            <tr><td class="l">{{ $a->date_label }}</td><td class="l">{{ $a->title }}</td><td class="l note">{{ $a->notes }}</td></tr>
        @empty
            <tr><td class="l muted" colspan="3">No entries.</td></tr>
        @endforelse
        </tbody>
    </table>
@endforeach

{{-- H Social --}}
<h2 style="margin-top:12px;">H · Social Media Insight ({{ $d->socialPlatform() }})</h2>
@php $slabels = \App\Livewire\Sections\SocialMedia::METRICS; @endphp
<table>
    <thead><tr><th class="l">Metric</th><th>Last Week</th><th>This Week</th><th>Growth</th><th>Growth %</th></tr></thead>
    <tbody>
    @foreach($d->socialMetrics() as $m)
        @php $g=$m->growth(); $gp=$m->growthPercent(); $c = $g===null?'':($g>=0?'color:#0a7d3c;':'color:#c0392b;'); @endphp
        <tr>
            <td class="l">{{ $slabels[$m->metric_key] ?? $m->metric_key }}</td>
            <td>{{ Format::number($m->last_week) }}</td><td>{{ Format::number($m->this_week) }}</td>
            <td style="{{ $c }}">{{ $g===null?'–':($g>=0?'+':'').Format::number($g) }}</td>
            <td style="{{ $c }}">{{ $gp===null?'–':($gp>=0?'+':'').number_format($gp,1).'%' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- I Training --}}
<h2 style="margin-top:12px;">I · Training</h2>
<table>
    <thead><tr><th class="l">Date</th><th class="l">Topic</th><th class="l">Duration</th><th class="l">Trainer</th><th class="l">Participants</th></tr></thead>
    <tbody>
    @forelse($d->trainings() as $tr)
        <tr><td class="l">{{ $tr->date_label }}</td><td class="l">{{ $tr->topic }}</td><td class="l">{{ $tr->duration }}</td><td class="l">{{ $tr->trainer }}</td><td class="l">{{ $tr->participants }}</td></tr>
    @empty
        <tr><td class="l muted" colspan="5">No entries.</td></tr>
    @endforelse
    </tbody>
</table>

{{-- J Action Plan --}}
<h2 style="margin-top:12px;">J · Next Week Action Plan</h2>
<table>
    <thead><tr><th class="l" style="width:14%;">Category</th><th class="l" style="width:22%;">Plan</th><th class="l">Start</th><th class="l">Deadline</th><th class="l">Remark</th></tr></thead>
    <tbody>
    @foreach($d->actionPlansByCategory() as $category => $plans)
        @foreach($plans as $pl)
            <tr><td class="l cat">{{ $category }}</td><td class="l">{{ $pl->plan }}</td><td class="l">{{ $pl->start_label }}</td><td class="l">{{ $pl->deadline_label }}</td><td class="l note">{{ $pl->remark }}</td></tr>
        @endforeach
    @endforeach
    </tbody>
</table>
<pagebreak />

{{-- OWNER OVERVIEW --}}
<h2>Owner Overview</h2>
<h3>1 · Repeater Guest — Room Performance</h3>
@php $rt = $d->ownerRepeaterTotals(); @endphp
<table>
    <thead><tr><th class="l">Month</th><th>Room Nights</th><th>ADR</th><th>Revenue</th></tr></thead>
    <tbody>
    @foreach($d->ownerRepeater() as $m)
        <tr><td class="l">{{ $m->label }}</td><td>{{ Format::number($m->room_nights) }}</td><td>{{ Format::idr($m->adr()) }}</td><td>{{ Format::idr($m->revenue) }}</td></tr>
    @endforeach
        <tr class="total"><td class="l">Total</td><td>{{ Format::number($rt['rn']) }}</td><td>{{ Format::idr($rt['adr']) }}</td><td>{{ Format::idr($rt['revenue']) }}</td></tr>
    </tbody>
</table>
<h3>2 · Channel Mix</h3>
@php $mt = $d->ownerMixTotals(); @endphp
<table>
    <thead><tr><th class="l">Source</th><th>RN Sold</th><th>ARR</th><th>Revenue</th><th>%</th></tr></thead>
    <tbody>
    @foreach($d->ownerMix() as $m)
        <tr><td class="l">{{ $m->label }}</td><td>{{ Format::number($m->rn_sold) }}</td><td>{{ Format::idr($m->arr()) }}</td><td>{{ Format::idr($m->gross_revenue) }}</td><td>{{ Format::percent(ReportCalculator::sharePercent($m->rn_sold, $mt['rn'])) }}</td></tr>
    @endforeach
        <tr class="total"><td class="l">Total</td><td>{{ Format::number($mt['rn']) }}</td><td>{{ Format::idr($mt['arr']) }}</td><td>{{ Format::idr($mt['revenue']) }}</td><td>100%</td></tr>
    </tbody>
</table>
