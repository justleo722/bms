<h1>Welcome to BMS, {{ $user->name }}</h1>
<p>Your administrator account for <strong>{{ $user->business_name }}</strong> has been created.</p>
<p>Your four-digit sign-in code is:</p>
<p style="font-size: 24px; font-weight: bold; letter-spacing: 0.2em;">{{ $signInCode }}</p>
<p>Use this code on the BMS sign-in screen. Keep it private.</p>
