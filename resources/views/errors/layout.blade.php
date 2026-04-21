<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') // {{ config('app.name', 'ClashMonitor') }}</title>
    <link rel="icon" type="image/svg+xml" href="/icon.svg">
    <meta name="theme-color" content="#3D7A32">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --black: #080A07;
            --surface-0: #0C0F0B;
            --surface-1: #111510;
            --surface-2: #181D16;
            --green-dim: #1A3018;
            --green-mid: #2D5426;
            --green-base: #3D7A32;
            --green-bright: #52A844;
            --green-neon: #6FD65A;
            --green-glow: #8FE87C;
            --text-primary: #D4E8CF;
            --text-secondary: #8AAD83;
            --text-muted: #4A6344;
            --text-dim: #2D3F2B;
            --border-mid: #243320;
            --red-bright: #E74C3C;
            --amber-bright: #F59E0B;
            --font-sans: 'Rajdhani', ui-sans-serif, system-ui, sans-serif;
            --font-display: 'Bebas Neue', sans-serif;
            --font-mono: 'Share Tech Mono', monospace;
        }

        body {
            background: var(--black);
            color: var(--text-primary);
            font-family: var(--font-sans);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Persistent scanlines ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 10;
            pointer-events: none;
            background: repeating-linear-gradient(
                0deg,
                transparent,
                transparent 2px,
                rgba(0, 0, 0, 0.06) 2px,
                rgba(0, 0, 0, 0.06) 4px
            );
        }

        /* ── Ambient glitch overlay ── */
        .glitch-overlay {
            position: fixed;
            inset: 0;
            z-index: 5;
            pointer-events: none;
            overflow: hidden;
        }

        /* Slow sweeping scanline */
        .glitch-sweep {
            position: absolute;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(111, 214, 90, 0.25) 20%,
                rgba(143, 232, 124, 0.45) 50%,
                rgba(111, 214, 90, 0.25) 80%,
                transparent 100%
            );
            box-shadow: 0 0 12px rgba(111, 214, 90, 0.15);
            animation: sweep 6s ease-in-out infinite;
        }

        /* Subtle periodic color shift */
        .glitch-tint {
            position: absolute;
            inset: 0;
            background: radial-gradient(
                ellipse at 50% 50%,
                rgba(111, 214, 90, 0.03),
                transparent 70%
            );
            animation: tint-pulse 4s ease-in-out infinite;
        }

        /* Flicker bands — very subtle horizontal distortion */
        .glitch-flicker {
            position: absolute;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(111, 214, 90, 0.06);
            animation: flicker-drift 8s linear infinite;
        }
        .glitch-flicker:nth-child(2) {
            animation-duration: 11s;
            animation-delay: -3s;
            height: 2px;
            background: rgba(111, 214, 90, 0.04);
        }
        .glitch-flicker:nth-child(3) {
            animation-duration: 14s;
            animation-delay: -7s;
            height: 1px;
            background: rgba(111, 214, 90, 0.05);
        }

        @keyframes sweep {
            0%, 100% { top: -2%; opacity: 0; }
            5%       { opacity: 1; }
            95%      { opacity: 0.6; }
            98%      { top: 102%; opacity: 0; }
        }

        @keyframes tint-pulse {
            0%, 100% { opacity: 0; }
            50%      { opacity: 1; }
        }

        @keyframes flicker-drift {
            0%   { top: -5%; }
            100% { top: 105%; }
        }

        /* ── Content card ── */
        .error-card {
            position: relative;
            z-index: 15;
            text-align: center;
            max-width: 480px;
            padding: 48px 40px;
        }

        /* Error code */
        .error-code {
            font-family: var(--font-display);
            font-size: clamp(80px, 15vw, 140px);
            line-height: 1;
            letter-spacing: 0.06em;
            color: var(--text-dim);
            position: relative;
        }

        .error-code::after {
            content: attr(data-code);
            position: absolute;
            inset: 0;
            color: var(--green-neon);
            opacity: 0;
            animation: code-glitch 6s ease-in-out infinite;
            clip-path: inset(0 0 0 0);
        }

        @keyframes code-glitch {
            0%, 100% { opacity: 0; }
            42%      { opacity: 0; }
            43%      { opacity: 0.4; clip-path: inset(20% 0 60% 0); transform: translateX(-2px); }
            44%      { opacity: 0; }
            46%      { opacity: 0.3; clip-path: inset(50% 0 20% 0); transform: translateX(3px); }
            47%      { opacity: 0; transform: translateX(0); }
            78%      { opacity: 0; }
            79%      { opacity: 0.25; clip-path: inset(30% 0 40% 0); transform: translateX(-1px); }
            80%      { opacity: 0; transform: translateX(0); }
        }

        /* Tactical divider */
        .error-divider {
            width: 60px;
            height: 1px;
            background: var(--green-mid);
            margin: 20px auto;
            position: relative;
        }

        .error-divider::before {
            content: '';
            position: absolute;
            left: 50%;
            top: -3px;
            width: 7px;
            height: 7px;
            background: var(--green-base);
            transform: translateX(-50%) rotate(45deg);
        }

        /* Title / message */
        .error-title {
            font-family: var(--font-mono);
            font-size: 13px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .error-message {
            font-family: var(--font-sans);
            font-size: 15px;
            font-weight: 400;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        /* Meta line */
        .error-meta {
            font-family: var(--font-mono);
            font-size: 10px;
            letter-spacing: 0.12em;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 28px;
        }

        .error-meta-sep {
            color: var(--green-dim);
        }

        .error-blink {
            display: inline-block;
            width: 5px;
            height: 9px;
            background: var(--green-base);
            animation: blink 1s step-end infinite;
        }

        @keyframes blink {
            50% { opacity: 0; }
        }

        /* CTA link */
        .error-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-mono);
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--green-bright);
            text-decoration: none;
            border: 1px solid var(--green-mid);
            padding: 10px 24px;
            transition: all 0.2s ease;
        }

        .error-link:hover {
            background: rgba(82, 168, 68, 0.08);
            border-color: var(--green-bright);
            box-shadow: 0 0 12px rgba(82, 168, 68, 0.15);
        }

        .error-link-arrow {
            font-size: 14px;
            transition: transform 0.2s ease;
        }

        .error-link:hover .error-link-arrow {
            transform: translateX(3px);
        }

        /* Corner brackets on card */
        .error-card::before,
        .error-card::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            border-color: var(--green-dim);
            border-style: solid;
            opacity: 0.5;
        }

        .error-card::before {
            top: 0;
            left: 0;
            border-width: 1px 0 0 1px;
        }

        .error-card::after {
            bottom: 0;
            right: 0;
            border-width: 0 1px 1px 0;
        }
    </style>
</head>
<body>
    {{-- Ambient glitch layer --}}
    <div class="glitch-overlay" aria-hidden="true">
        <div class="glitch-tint"></div>
        <div class="glitch-sweep"></div>
        <div class="glitch-flicker"></div>
        <div class="glitch-flicker"></div>
        <div class="glitch-flicker"></div>
    </div>

    <div class="error-card">
        <div class="error-code" data-code="@yield('code')">@yield('code')</div>
        <div class="error-divider"></div>
        <div class="error-title">@yield('title')</div>
        <div class="error-message">@yield('message')</div>
        <div class="error-meta">
            <span>CLASHMONITOR</span>
            <span class="error-meta-sep">/</span>
            <span>{{ now()->format('Y-m-d H:i') }} UTC</span>
            <span class="error-meta-sep">/</span>
            <span class="error-blink"></span>
        </div>
        <a href="/" class="error-link">
            <span>Return to dashboard</span>
            <span class="error-link-arrow">&rarr;</span>
        </a>
    </div>
</body>
</html>
