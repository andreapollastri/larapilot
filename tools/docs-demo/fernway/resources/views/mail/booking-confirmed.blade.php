@php($session = $booking->session)
<!doctype html>
<html lang="en">
<body style="margin:0;background:#f3efe8;font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;color:#1d2a24">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 12px">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:14px;overflow:hidden">
<tr><td style="background:#2f6b55;color:#fff;padding:22px 28px;font-size:22px;font-weight:700">fernway</td></tr>
<tr><td style="padding:28px">
<p style="margin:0 0 4px;font-size:15px">Hi {{ $booking->user->name }},</p>
<h1 style="margin:0 0 18px;font-size:24px">{{ ($promoted ?? false) ? 'A spot opened: you are in' : "You're booked into" }} {{ $session->classType->name }}</h1>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e6e1d8;border-radius:10px;font-size:15px">
<tr><td style="padding:12px 16px;color:#6b746f;width:35%">When</td><td style="padding:12px 16px;font-weight:600">{{ $session->starts_at->format('l j F, H:i') }}</td></tr>
<tr><td style="padding:12px 16px;color:#6b746f;border-top:1px solid #e6e1d8">With</td><td style="padding:12px 16px;border-top:1px solid #e6e1d8">{{ $session->instructor->display_name }}</td></tr>
<tr><td style="padding:12px 16px;color:#6b746f;border-top:1px solid #e6e1d8">Where</td><td style="padding:12px 16px;border-top:1px solid #e6e1d8">{{ $session->classType->studio->name }}, {{ $session->room }}</td></tr>
</table>
<p style="font-size:15px;margin:18px 0">Free cancellation until {{ $session->starts_at->copy()->subMinutes($session->classType->studio->late_cancel_minutes)->format('H:i') }}. After that the class credit is used.</p>
<a href="{{ url('/bookings/'.$booking->id) }}" style="display:inline-block;background:#2f6b55;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:8px">View booking</a>
</td></tr>
</table>
<p style="font-size:12px;color:#6b746f">Fernway · class bookings for independent studios</p>
</td></tr></table>
</body>
</html>
