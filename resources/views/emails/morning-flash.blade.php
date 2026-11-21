@php
    $ar = app()->getLocale() === 'ar';
    $dir = $ar ? 'rtl' : 'ltr';
    $start = $ar ? 'right' : 'left';
    $end = $ar ? 'left' : 'right';
    $nm = fn ($h) => $ar && !empty($h['name_ar']) ? $h['name_ar'] : $h['name'];
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
    $levels = ['critical' => '#F15F5A', 'warning' => '#FCC5A9', 'info' => '#889594'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<body style="margin:0;background:#F8F7F4;font-family:{{ $font }};color:#262E43;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F7F4;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:760px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #ECE9E3;" dir="{{ $dir }}">
    <tr><td style="background:#262E43;padding:24px 28px;">
        <div style="color:#F15F5A;font-size:11px;font-weight:700;letter-spacing:{{ $ar ? '0' : '2px' }};text-transform:uppercase;">{{ __('Group flash') }}</div>
        <div style="color:#ffffff;font-size:24px;font-weight:700;margin-top:6px;font-family:'El Messiri',{{ $font }};">{{ $ar ? ($group->name_ar ?: $group->name) : $group->name }}</div>
        <div style="color:#C3CBCA;font-size:13px;margin-top:4px;">{{ __('Business date') }}: {{ $report['date'] }}</div>
    </td></tr>

    @foreach (['day' => __('Day'), 'mtd' => __('Month to date')] as $key => $title)
        @php $p = $report['periods'][$key]; @endphp
        <tr><td style="padding:22px 28px 6px;">
            <div style="font-size:16px;font-weight:700;">{{ $title }} <span style="color:#889594;font-size:12px;font-weight:400;">· {{ __('vs last year') }} · {{ $p['currency'] }}</span></div>
        </td></tr>
        <tr><td style="padding:0 20px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;">
                <tr style="background:#262E43;color:#ffffff;">
                    <th style="padding:10px;text-align:{{ $start }};font-size:11px;"></th>
                    @foreach ($p['hotels'] as $h)
                        <th style="padding:10px;text-align:{{ $end }};font-size:11px;font-weight:700;">{{ $nm($h['ty']) }}</th>
                    @endforeach
                    <th style="padding:10px;text-align:{{ $end }};font-size:11px;font-weight:700;background:#F15F5A;">{{ __('Group') }}</th>
                </tr>
                @foreach (\App\Reports\MorningFlashBuilder::ROWS as [$field, $label, $kind])
                    <tr style="border-bottom:1px solid #ECE9E3;">
                        <td style="padding:9px 10px;text-align:{{ $start }};font-weight:600;color:#5B6374;">{{ __($label) }}</td>
                        @foreach ([...$p['hotels'], $p['group']] as $i => $h)
                            @php $d = $delta($h['ty'][$field] ?? null, $h['ly'][$field] ?? null, $kind); $isGroup = $i === count($p['hotels']); @endphp
                            <td style="padding:9px 10px;text-align:{{ $end }};{{ $isGroup ? 'background:#FDEBE9;' : '' }}">
                                <div style="font-weight:700;" dir="ltr">{{ $fmt($h['ty'][$field] ?? null, $kind) }}</div>
                                @if ($d)
                                    <div style="font-size:11px;color:{{ $d[0] >= 0 ? '#3F7A6F' : '#D94B46' }};" dir="ltr">{{ $d[0] >= 0 ? '▲' : '▼' }} {{ $d[1] }}</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
            @if (count($p['missing']))
                <div style="margin-top:8px;font-size:12px;color:#D94B46;">{{ __('No report received yet for this date from:') }} {{ collect($p['missing'])->map(fn ($m) => $nm($m))->join('، ') }}</div>
            @endif
        </td></tr>
    @endforeach

    @if (count($report['attention']))
        <tr><td style="padding:24px 28px 8px;"><div style="font-size:16px;font-weight:700;">{{ __('Needs attention') }}</div></td></tr>
        <tr><td style="padding:0 28px 8px;">
            @foreach ($report['attention'] as $item)
                <div style="border-{{ $start }}:4px solid {{ $levels[$item['level']] }};background:#F8F7F4;border-radius:8px;padding:10px 12px;margin-bottom:8px;font-size:13px;">
                    <b>{{ $item['hotel'] ? $nm($item['hotel']) : __('Group') }}</b> · {{ __('attention_'.$item['type'], $item['params']) }}
                </div>
            @endforeach
        </td></tr>
    @endif

    <tr><td style="padding:20px 28px 28px;">
        <a href="{{ url('/manager-flash?view=compare&date='.$report['date']) }}" style="display:inline-block;background:#F15F5A;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:12px;">{{ __('Open the full report') }}</a>
        <div style="margin-top:14px;font-size:11px;color:#889594;">{{ __('Occupancy, ADR and RevPAR follow USALI: complimentary and house-use rooms are not rooms sold.') }}</div>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
