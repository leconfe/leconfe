<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $exception?->getMessage() ?: 'We’ll be right back' }} &middot; Leconfe</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            color: #1f2937;
            background: linear-gradient(180deg, #F1F6FA 0%, #e6eef7 100%);
            -webkit-font-smoothing: antialiased;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 560px;
            background: #ffffff;
            border: 1px solid rgba(28, 53, 105, 0.08);
            border-radius: 16px;
            padding: 48px 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(28, 53, 105, 0.08);
        }
        .logo {
            display: block;
            height: 56px;
            width: auto;
            margin: 0 auto 20px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(28, 53, 105, 0.08);
            color: #1c3569;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 999px;
            margin-bottom: 20px;
            letter-spacing: 0.02em;
        }
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #1c3569;
            animation: pulse 1.6s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.4; transform: scale(0.8); }
        }
        h1 {
            font-size: 28px;
            line-height: 1.25;
            margin: 0 0 12px;
            color: #1c3569;
            font-weight: 700;
        }
        p {
            margin: 0 0 16px;
            font-size: 16px;
            line-height: 1.6;
            color: #4b5563;
        }
        p.detail {
            margin-top: 24px;
            font-size: 14px;
            color: #6b7280;
        }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #eef2f7;
            font-size: 13px;
            color: #9ca3af;
        }
        .footer a {
            color: #1c3569;
            text-decoration: none;
            font-weight: 500;
        }
        .footer a:hover { text-decoration: underline; }
        @media (max-width: 480px) {
            .card { padding: 36px 24px; }
            h1 { font-size: 22px; }
            p { font-size: 15px; }
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <img src="{{ asset('logo.png') }}" alt="Leconfe" class="logo">

        <span class="badge"><span class="dot"></span> Scheduled maintenance</span>

        <h1>We’ll be right back.</h1>

        <p>
            Leconfe is undergoing maintenance to improve your conference management experience.
            Submissions, registrations, and proceedings will be available again shortly.
        </p>

        @if(isset($exception) && $exception->getMessage())
            <p class="detail">{{ $exception->getMessage() }}</p>
        @endif

        <div class="footer">
            Thank you for your patience. &middot; <a href="/">Try again</a>
        </div>
    </main>
</body>
</html>
