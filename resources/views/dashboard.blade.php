<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Cat Finder — RSPCA ACT watcher</title>
    <style>
        :root {
            --bg: #0f1115;
            --panel: #181b22;
            --panel-2: #1f232c;
            --border: #2a2f3a;
            --text: #e6e9ef;
            --muted: #9aa3b2;
            --accent: #ff8a3d;
            --green: #34d399;
            --red: #f87171;
            --yellow: #fbbf24;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
        }
        .wrap { max-width: 920px; margin: 0 auto; padding: 32px 20px 80px; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 8px; }
        h1 { font-size: 24px; margin: 0; display: flex; align-items: center; gap: 10px; }
        .sub { color: var(--muted); font-size: 14px; margin: 4px 0 24px; }
        .sub a { color: var(--accent); }
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 16px; }
        .card .num { font-size: 28px; font-weight: 700; }
        .card .label { color: var(--muted); font-size: 13px; }
        .btn {
            background: var(--accent); color: #1a1205; border: none; border-radius: 10px;
            padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn:hover { filter: brightness(1.08); }
        .flash { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .flash.ok { background: rgba(52,211,153,.12); border: 1px solid rgba(52,211,153,.4); color: var(--green); }
        .flash.err { background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.4); color: var(--red); }
        .flash.warn { background: rgba(251,191,36,.1); border: 1px solid rgba(251,191,36,.35); color: var(--yellow); }
        h2 { font-size: 16px; margin: 32px 0 12px; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
        .log { display: flex; flex-direction: column; gap: 10px; }
        .entry { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; padding: 14px 16px; }
        .entry .top { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .pill { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; padding: 3px 9px; border-radius: 999px; }
        .pill.sent { background: rgba(52,211,153,.15); color: var(--green); }
        .pill.failed { background: rgba(248,113,113,.15); color: var(--red); }
        .pill.skipped { background: rgba(154,163,178,.15); color: var(--muted); }
        .entry .when { color: var(--muted); font-size: 12px; margin-left: auto; }
        .entry .to { color: var(--muted); font-size: 12px; }
        .entry pre { white-space: pre-wrap; word-break: break-word; font-family: inherit; font-size: 13px; margin: 10px 0 0; color: var(--text); }
        .entry .error { color: var(--red); font-size: 12px; margin-top: 8px; }
        .empty { color: var(--muted); font-size: 14px; padding: 24px; text-align: center; border: 1px dashed var(--border); border-radius: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 600; }
        td a { color: var(--accent); text-decoration: none; }
        .new-dot { color: var(--green); font-size: 11px; }
        .muted { color: var(--muted); }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1><span>&#128049;</span> Cat Finder</h1>
        <form method="POST" action="{{ route('check') }}">
            @csrf
            <button class="btn" type="submit">Run check now</button>
        </form>
    </header>
    <p class="sub">
        Watches the <a href="https://rspca-act.org.au/adopt-pet?field_animal_type=cat%3Bkitten&field_location=All" target="_blank" rel="noopener">RSPCA ACT cat &amp; kitten listings</a>
        every hour and texts you when a new one appears.
    </p>

    @if (session('status'))
        <div class="flash ok">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="flash err">{{ session('error') }}</div>
    @endif
    @unless ($twilioConfigured)
        <div class="flash warn">Twilio isn&rsquo;t fully configured yet &mdash; set <code>TWILIO_ACCOUNT_SID</code>, <code>TWILIO_AUTH_TOKEN</code>, <code>TWILIO_FROM</code> and <code>TWILIO_TO</code> to enable texts.</div>
    @endunless

    <div class="cards">
        <div class="card">
            <div class="num">{{ $animals->count() }}</div>
            <div class="label">Animals tracked</div>
        </div>
        <div class="card">
            <div class="num">{{ $logs->where('status', 'sent')->count() }}</div>
            <div class="label">Alerts sent</div>
        </div>
        <div class="card">
            <div class="num">{{ optional($logs->first())->created_at?->diffForHumans() ?? '—' }}</div>
            <div class="label">Last activity</div>
        </div>
        <div class="card">
            <div class="num" style="font-size:16px; word-break:break-all;">{{ $recipient ?: '—' }}</div>
            <div class="label">Texting</div>
        </div>
    </div>

    <h2>Notification log</h2>
    <div class="log">
        @forelse ($logs as $log)
            <div class="entry">
                <div class="top">
                    <span class="pill {{ $log->status }}">{{ $log->status }}</span>
                    <span class="to">{{ $log->channel }} &middot; {{ $log->recipient ?: 'no recipient' }} &middot; {{ $log->animal_count }} animal(s)</span>
                    <span class="when">{{ $log->created_at?->format('M j, Y g:i A') }}</span>
                </div>
                @if ($log->body)
                    <pre>{{ $log->body }}</pre>
                @endif
                @if ($log->error)
                    <div class="error">{{ $log->error }}</div>
                @endif
            </div>
        @empty
            <div class="empty">No notifications yet. Hit &ldquo;Run check now&rdquo; to establish a baseline.</div>
        @endforelse
    </div>

    <h2>Currently tracked</h2>
    @if ($animals->isEmpty())
        <div class="empty">Nothing tracked yet.</div>
    @else
        <table>
            <thead>
                <tr><th>Name</th><th>Type</th><th>Age</th><th>Site</th><th>First seen</th></tr>
            </thead>
            <tbody>
                @foreach ($animals as $a)
                    <tr>
                        <td>
                            <a href="{{ $a->url }}" target="_blank" rel="noopener">{{ $a->name }}</a>
                            @unless ($a->notified)<span class="new-dot">&#9679; new</span>@endunless
                        </td>
                        <td>{{ $a->type }}</td>
                        <td>{{ $a->age }}</td>
                        <td>{{ $a->site }}</td>
                        <td class="muted">{{ $a->first_seen_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</body>
</html>
