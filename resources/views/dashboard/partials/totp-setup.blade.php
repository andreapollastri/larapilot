{{--
    The enrolment of an authenticator: the steps, the QR code, the secret
    for whoever types it, and the code that confirms it. Used at the door
    (production) and from the account page (by choice).
--}}
<ol class="totp-steps">
    <li>Install an authenticator app if you have none — Google Authenticator, Microsoft Authenticator, 1Password, Authy, or any app that reads a QR code.</li>
    <li>Scan this code with the app, or type the secret into it.</li>
    <li>Enter the six digits the app shows to confirm.</li>
</ol>

<div class="totp-pair">
    <div class="totp-qr" aria-label="QR code for the authenticator">{!! $qr !!}</div>
    <div class="totp-secret">
        <p>The app will show the account as <strong>{{ $issuer }}</strong> · <strong>{{ $username }}</strong>.</p>
        <p>Cannot scan? Type this secret (time-based, SHA-1, six digits, thirty seconds):</p>
        <code>{{ $secret_pretty }}</code>
    </div>
</div>

@if ($errors->any())
    <div class="alert error" role="alert">{{ $errors->first() }}</div>
@endif

<form method="post" action="{{ $action }}">
    @csrf
    <label class="field">
        <span>Six-digit code from the app</span>
        <input class="control otp" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" maxlength="7" required autofocus>
    </label>
    <button type="submit" class="btn">Confirm and turn it on</button>
</form>
