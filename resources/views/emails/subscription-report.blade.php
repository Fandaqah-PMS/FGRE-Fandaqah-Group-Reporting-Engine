@php
    /*
     * Subscription email. Email clients ignore SVG and most CSS, so charts are drawn with
     * table cells (bars = cell widths / heights). Colours from the validated chart palette:
     * this period navy, last year light grey; text stays in ink colours.
     */
    $ar = app()->getLocale() === 'ar';
    $dir = $ar ? 'rtl' : 'ltr';
    $start = $ar ? 'right' : 'left';
    $end = $ar ? 'left' : 'right';
    $nm = fn ($h) => $ar && !empty($h['name_ar']) ? $h['name_ar'] : ($h['name'] ?? '');
    $fmt = function ($v, $kind) {
        if ($v === null || $v === '') return '–';
        return match ($kind) {
            'pct' => number_format((float) $v, 1).'%',
            'money' => number_format((float) $v, 2),
            'money0' => number_format((float) $v, 0),
            default => number_format((float) $v, 0),
        };
    };
    $delta = function ($ty, $ly, $kind) {
        if ($ty === null || $ly === null) return null;
        if ($kind === 'pct') { $d = (float) $ty - (float) $ly; return [$d, sprintf('%+.1f pp', $d)]; }
        if ((float) $ly == 0.0) return null;
        $d = ((float) $ty / (float) $ly - 1) * 100; return [$d, sprintf('%+.1f%%', $d)];
    };
    $font = $ar ? "Almarai, Tahoma, Arial, sans-serif" : "Montserrat, 'Segoe UI', Arial, sans-serif";
    $navy = '#262E43'; $coral = '#F15F5A'; $grey = '#C3CBCA'; $muted = '#889594'; $ink2 = '#5B6374';
    $up = '#3F7A6F'; $down = '#D94B46';
    $levels = [
        'critical' => ['#F15F5A', '#FDEBE9', __('Action needed')],
        'warning' => ['#E8A27F', '#FEF3EC', __('Watch')],
        'info' => ['#889594', '#F1F3F3', __('Note')],
    ];
    $titles = ['day' => __('Day'), 'week' => __('Last 7 days'), 'month' => $periodLabel, 'mtd' => __('Month to date'), 'ytd' => __('Year to date')];
    $main = $report['sections'][$report['main']];
    $currency = $main['currency'];
    $charts = $subscription->wantsCharts();
    $tables = $subscription->wantsTables();
    $tiles = [['occupancy_pct', __('Occupancy'), 'pct'], ['adr', __('ADR'), 'money'], ['revpar', __('RevPAR'), 'money'], ['total_revenue', __('Total revenue'), 'money0']];
    $reportUrl = url($report['frequency'] === 'daily'
        ? '/manager-flash?view=compare&date='.$report['to']
        : '/manager-flash?view=compare&period=mtd&date='.$report['to']);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Group report') }}</title></head>
<body style="margin:0;background:#F8F7F4;font-family:{{ $font }};color:{{ $navy }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F7F4;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #ECE9E3;" dir="{{ $dir }}">

    {{-- Header --}}
    <tr><td style="background:{{ $navy }};padding:24px 28px;">
        <div style="color:{{ $coral }};font-size:11px;font-weight:700;letter-spacing:{{ $ar ? '0' : '2px' }};text-transform:uppercase;">{{ __(ucfirst($report['frequency']).' group report') }}</div>
        <div style="color:#ffffff;font-size:24px;font-weight:700;margin-top:6px;font-family:'El Messiri',{{ $font }};">{{ $ar ? ($group->name_ar ?: $group->name) : $group->name }}</div>
        <div style="color:{{ $grey }};font-size:13px;margin-top:4px;"><span dir="ltr">{{ $periodLabel }}</span> · {{ __(':n hotels', ['n' => count($main['hotels'])]) }} · {{ $currency }}</div>
    </td></tr>

    @if ($charts)
        {{-- Group KPI tiles --}}
        <tr><td style="padding:22px 20px 4px;">
            <div style="font-size:16px;font-weight:700;padding:0 8px 10px;">{{ __('Group') }} · {{ $titles[$report['main']] }} <span style="color:{{ $muted }};font-size:12px;font-weight:400;">· {{ __('vs last year') }}</span></div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                @foreach ($tiles as [$field, $label, $kind])
                    @php $d = $delta($main['group']['ty'][$field] ?? null, $main['group']['ly'][$field] ?? null, $kind); @endphp
                    <td width="25%" valign="top" style="padding:0 6px;">
                        <div style="background:#F8F7F4;border-radius:12px;padding:12px;">
                            <div style="font-size:11px;color:{{ $ink2 }};font-weight:700;">{{ $label }}</div>
                            <div style="font-size:18px;font-weight:600;margin-top:4px;font-family:Montserrat,'Segoe UI',Arial,sans-serif;" dir="ltr">{{ $fmt($main['group']['ty'][$field] ?? null, $kind) }}</div>
                            @if ($d)
                                <div style="font-size:11px;font-weight:700;margin-top:2px;color:{{ $d[0] >= 0 ? $up : $down }};" dir="ltr">{{ $d[0] >= 0 ? '▲' : '▼' }} {{ $d[1] }}</div>
                            @else
                                <div style="font-size:11px;margin-top:2px;color:{{ $muted }};">&nbsp;</div>
                            @endif
                        </div>
                    </td>
                @endforeach
            </tr></table>
        </td></tr>

        {{-- Daily trend (weekly / monthly) --}}
        @if (count($report['trend']) > 1)
            @php $maxRev = max(1, ...array_map(fn ($p) => max((float) $p['total_revenue'], (float) ($p['ly_total_revenue'] ?? 0)), $report['trend'])); [$bw, $bl] = count($report['trend']) <= 10 ? [18, 9] : [6, 4]; @endphp
            <tr><td style="padding:22px 28px 4px;">
                <div style="font-size:15px;font-weight:700;">{{ __('Daily total revenue, all hotels') }}</div>
                <div style="font-size:11px;color:{{ $muted }};margin-top:2px;">
                    <span style="display:inline-block;width:10px;height:10px;background:{{ $navy }};border-radius:3px;vertical-align:middle;"></span> {{ __('This year') }}
                    &nbsp;&nbsp;<span style="display:inline-block;width:10px;height:10px;background:{{ $grey }};border-radius:3px;vertical-align:middle;"></span> {{ __('Last year') }}
                </div>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;" dir="ltr"><tr>
                    @foreach ($report['trend'] as $p)
                        @php $h = (int) round(110 * (float) $p['total_revenue'] / $maxRev); $hl = (int) round(110 * (float) ($p['ly_total_revenue'] ?? 0) / $maxRev); @endphp
                        <td valign="bottom" align="center" style="height:112px;padding:0 1px;" title="{{ $p['date'] }}: {{ $fmt($p['total_revenue'], 'money0') }}">
                            <table role="presentation" cellpadding="0" cellspacing="0" align="center"><tr>
                                <td valign="bottom" style="padding:0 1px 0 0;"><div style="width:{{ $bw }}px;height:{{ max(1, $h) }}px;background:{{ $navy }};border-radius:3px 3px 0 0;font-size:0;line-height:0;">&nbsp;</div></td>
                                <td valign="bottom"><div style="width:{{ $bl }}px;height:{{ max(1, $hl) }}px;background:{{ $grey }};border-radius:3px 3px 0 0;font-size:0;line-height:0;">&nbsp;</div></td>
                            </tr></table>
                        </td>
                    @endforeach
                </tr><tr>
                    @foreach ($report['trend'] as $i => $p)
                        <td align="center" style="font-size:9px;color:{{ $muted }};padding-top:4px;border-top:1px solid #ECE9E3;">{{ count($report['trend']) <= 10 || $i % 3 === 0 ? (int) substr($p['date'], 8, 2) : '' }}</td>
                    @endforeach
                </tr></table>
            </td></tr>
        @endif

        {{-- Hotel bar charts --}}
        @if (count($main['hotels']) > 0)
            @foreach (\App\Reports\Subscriptions\SubscriptionReportBuilder::CHARTS as [$field, $title, $kind])
                @php
                    $max = max(1, ...array_map(fn ($h) => max((float) ($h['ty'][$field] ?? 0), (float) ($h['ly'][$field] ?? 0)), $main['hotels']));
                    $groupValue = $main['group']['ty'][$field] ?? null;
                @endphp
                <tr><td style="padding:22px 28px 4px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                        <td style="font-size:15px;font-weight:700;">{{ __($title) }}</td>
                        <td style="text-align:{{ $end }};font-size:11px;color:{{ $muted }};">
                            {{ $field === 'total_revenue' ? __('Group total') : __('Group') }}: <b style="color:{{ $navy }};" dir="ltr">{{ $fmt($groupValue, $kind) }}</b>
                        </td>
                    </tr></table>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;">
                        @foreach ($main['hotels'] as $h)
                            @php
                                $v = $h['ty'][$field] ?? null; $l = $h['ly'][$field] ?? null; $d = $delta($v, $l, $kind);
                                $w = max(1, min(100, (int) round(100 * (float) $v / $max))); $wl = max(1, min(100, (int) round(100 * (float) $l / $max)));
                            @endphp
                            <tr>
                                <td style="padding:8px 0 3px;font-size:12px;font-weight:700;color:{{ $navy }};">{{ $nm($h['ty']) }}</td>
                                <td style="padding:8px 0 3px;font-size:12px;text-align:{{ $end }};white-space:nowrap;">
                                    <b dir="ltr">{{ $fmt($v, $kind) }}</b>
                                    @if ($d)<span style="font-size:11px;font-weight:700;color:{{ $d[0] >= 0 ? $up : $down }};" dir="ltr">&nbsp;{{ $d[0] >= 0 ? '▲' : '▼' }} {{ $d[1] }}</span>@endif
                                </td>
                            </tr>
                            <tr><td colspan="2">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                                    <td width="{{ $w }}%" style="background:{{ $navy }};height:10px;border-radius:4px;font-size:0;line-height:0;">&nbsp;</td>
                                    @if ($w < 100)<td style="font-size:0;line-height:0;">&nbsp;</td>@endif
                                </tr></table>
                                @if ($l !== null)
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:3px;"><tr>
                                        <td width="{{ $wl }}%" style="background:{{ $grey }};height:5px;border-radius:3px;font-size:0;line-height:0;">&nbsp;</td>
                                        @if ($wl < 100)<td style="font-size:0;line-height:0;">&nbsp;</td>@endif
                                    </tr></table>
                                @endif
                            </td></tr>
                        @endforeach
                    </table>
                    <div style="font-size:10px;color:{{ $muted }};margin-top:6px;">
                        <span style="display:inline-block;width:10px;height:6px;background:{{ $navy }};border-radius:2px;"></span> {{ $titles[$report['main']] }}
                        &nbsp;&nbsp;<span style="display:inline-block;width:10px;height:4px;background:{{ $grey }};border-radius:2px;"></span> {{ __('Last year') }}
                    </div>
                </td></tr>
            @endforeach
        @endif
    @endif

    @if ($tables)
        {{-- Side-by-side tables --}}
        @foreach ($report['sections'] as $key => $p)
            <tr><td style="padding:24px 28px 6px;">
                <div style="font-size:16px;font-weight:700;">{{ $titles[$key] }} <span style="color:{{ $muted }};font-size:12px;font-weight:400;">· {{ __('vs last year') }} · {{ $p['currency'] }}</span></div>
            </td></tr>
            <tr><td style="padding:0 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                    <tr style="background:{{ $navy }};color:#ffffff;">
                        <th style="padding:10px;text-align:{{ $start }};font-size:11px;"></th>
                        @foreach ($p['hotels'] as $h)
                            <th style="padding:10px;text-align:{{ $end }};font-size:11px;font-weight:700;">{{ $nm($h['ty']) }}</th>
                        @endforeach
                        <th style="padding:10px;text-align:{{ $end }};font-size:11px;font-weight:700;background:{{ $coral }};">{{ __('Group') }}</th>
                    </tr>
                    @foreach (\App\Reports\Subscriptions\SubscriptionReportBuilder::ROWS as [$field, $label, $kind])
                        <tr style="border-bottom:1px solid #ECE9E3;">
                            <td style="padding:9px 10px;text-align:{{ $start }};font-weight:600;color:{{ $ink2 }};">{{ __($label) }}</td>
                            @foreach ([...$p['hotels'], $p['group']] as $i => $h)
                                @php $d = $delta($h['ty'][$field] ?? null, $h['ly'][$field] ?? null, $kind); $isGroup = $i === count($p['hotels']); @endphp
                                <td style="padding:9px 10px;text-align:{{ $end }};{{ $isGroup ? 'background:#FDEBE9;' : '' }}">
                                    <div style="font-weight:600;" dir="ltr">{{ $fmt($h['ty'][$field] ?? null, $kind) }}</div>
                                    @if ($d)
                                        <div style="font-size:11px;color:{{ $d[0] >= 0 ? $up : $down }};" dir="ltr">{{ $d[0] >= 0 ? '▲' : '▼' }} {{ $d[1] }}</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            </td></tr>
        @endforeach
    @endif

    @if (count($main['missing']))
        <tr><td style="padding:12px 28px 0;">
            <div style="font-size:12px;color:{{ $down }};">{{ __('No report received yet for this date from:') }} {{ collect($main['missing'])->map(fn ($m) => $nm($m))->join($ar ? '، ' : ', ') }}</div>
        </td></tr>
    @endif

    {{-- Needs attention, grouped by hotel --}}
    @if (count($report['attention']))
        <tr><td style="padding:24px 28px 8px;"><div style="font-size:16px;font-weight:700;">{{ __('Needs attention') }}</div></td></tr>
        <tr><td style="padding:0 28px 8px;">
            @foreach (collect($report['attention'])->groupBy(fn ($i) => $i['hotel']['id'] ?? 0) as $items)
                @php $lvl = $levels[$items->first()['level']]; $hotel = $items->first()['hotel']; @endphp
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px;border:1px solid #ECE9E3;border-radius:12px;border-collapse:separate;">
                    <tr><td style="padding:10px 14px;background:{{ $lvl[1] }};border-radius:12px 12px 0 0;">
                        <b style="font-size:13px;">{{ $hotel ? $nm($hotel) : __('Group') }}</b>
                        <span style="float:{{ $end }};font-size:11px;font-weight:700;color:{{ $navy }};"><span style="color:{{ $lvl[0] }};">●</span> {{ $lvl[2] }}</span>
                    </td></tr>
                    @foreach ($items as $item)
                        <tr><td style="padding:8px 14px;font-size:13px;color:{{ $ink2 }};border-top:1px solid #ECE9E3;">
                            <span style="color:{{ $levels[$item['level']][0] }};">■</span>&nbsp; {{ __('attention_'.$item['type'], array_map(fn ($v) => is_string($v) && preg_match('/^\d{4}-\d\d-\d\d$/', $v) ? "\u{2066}{$v}\u{2069}" : $v, $item['params'])) }}
                        </td></tr>
                    @endforeach
                </table>
            @endforeach
        </td></tr>
    @endif

    {{-- Footer --}}
    <tr><td style="padding:20px 28px 28px;">
        <a href="{{ $reportUrl }}" style="display:inline-block;background:{{ $coral }};color:#ffffff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px;">{{ __('Open the full report') }}</a>
        @if ($tables)
            <div style="margin-top:12px;font-size:12px;color:{{ $ink2 }};">{{ __('The figures are attached as a CSV file (opens in Excel).') }}</div>
        @endif
        <div style="margin-top:14px;font-size:11px;color:{{ $muted }};">{{ __('Occupancy, ADR and RevPAR follow USALI: complimentary and house-use rooms are not rooms sold.') }}</div>
        <div style="margin-top:6px;font-size:11px;color:{{ $muted }};">
            {{ __('You receive this email because you subscribed in FGRE.') }}
            <a href="{{ url('/subscriptions') }}" style="color:{{ $coral }};">{{ __('Manage subscriptions') }}</a>
        </div>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
