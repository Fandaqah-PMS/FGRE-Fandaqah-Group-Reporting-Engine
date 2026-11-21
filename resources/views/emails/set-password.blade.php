@php
    app()->setLocale($locale);
    $ar = $locale === 'ar';
    $dir = $ar ? 'rtl' : 'ltr';
    $font = $ar ? "Almarai, Tahoma, Arial, sans-serif" : "Montserrat, 'Segoe UI', Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;background:#F8F7F4;font-family:{{ $font }};color:#262E43;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F7F4;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #ECE9E3;" dir="{{ $dir }}">
    <tr><td style="background:#262E43;padding:24px 28px;">
        <div style="color:#F15F5A;font-size:11px;font-weight:700;letter-spacing:{{ $ar ? '0' : '2px' }};text-transform:uppercase;">{{ __('Fandaqah Group Reporting') }}</div>
        <div style="color:#ffffff;font-size:22px;font-weight:700;margin-top:6px;font-family:'El Messiri',{{ $font }};">
            {{ $invitedTo ? __('You are invited to :group', ['group' => $invitedTo]) : __('Reset your password') }}
        </div>
    </td></tr>
    <tr><td style="padding:28px;font-size:14px;line-height:1.7;">
        <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $name]) }}</p>
        @if ($invitedTo)
            <p style="margin:0 0 12px;">{{ $invitedBy ? __(':by added you to :group on FGRE, the group reporting engine.', ['by' => $invitedBy, 'group' => $invitedTo]) : __('You were added to :group on FGRE, the group reporting engine.', ['group' => $invitedTo]) }}</p>
            <p style="margin:0 0 22px;">{{ __('Choose a password to sign in. The link is valid for 7 days.') }}</p>
        @else
            <p style="margin:0 0 22px;">{{ __('We received a request to reset your password. The link is valid for 60 minutes. If you did not ask for it, ignore this email.') }}</p>
        @endif
        <a href="{{ $url }}" style="display:inline-block;background:#F15F5A;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 24px;border-radius:12px;">
            {{ $invitedTo ? __('Set my password') : __('Reset password') }}
        </a>
        <p style="margin:22px 0 0;font-size:12px;color:#889594;">{{ __('If the button does not work, copy this link into your browser:') }}<br><span dir="ltr" style="word-break:break-all;">{{ $url }}</span></p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
