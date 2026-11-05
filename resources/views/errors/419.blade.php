<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Session Expired</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #0f172a 100%);
            color: #e2e8f0;
        }
        .card {
            width: 100%;
            max-width: 440px;
            padding: 40px 32px;
            text-align: center;
            background: rgba(15, 23, 42, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 20px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
        }
        .icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: rgba(245, 158, 11, 0.12);
            color: #fbbf24;
        }
        h1 { margin: 0 0 10px; font-size: 22px; letter-spacing: -0.01em; }
        p { margin: 0 0 28px; font-size: 14px; line-height: 1.6; color: #94a3b8; }
        a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 13px 20px;
            border-radius: 12px;
            background: #2563eb;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
        }
        a:hover { background: #1d4ed8; }
        .hint { margin-top: 16px; margin-bottom: 0; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <main class="card">
        <div class="icon" aria-hidden="true">
            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h1>Your session expired</h1>
        <p>For your security, this page is no longer valid. Please sign in again to continue.</p>
        <a href="{{ route('login') }}">Go to sign in</a>
        <p class="hint">Your work already saved is safe.</p>
    </main>
</body>
</html>
