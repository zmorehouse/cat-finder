<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>catfinder</title>
    <style>
        :root {
            --bg: #0a0a0a;
            --surface: #101010;
            --surface-2: #151515;
            --border: #242424;
            --border-strong: #3a3a3a;
            --text: #e6e6e6;
            --muted: #7a7a7a;
            --dim: #555;
        }
        * { box-sizing: border-box; border-radius: 0 !important; }
        html, body { margin: 0; padding: 0; }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Monaco, "Cascadia Code", Consolas, "Liberation Mono", monospace;
            font-size: 13px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        a { color: var(--text); text-decoration: none; }
        a:hover { text-decoration: underline; }

        .topbar {
            border-bottom: 1px solid var(--border);
            position: sticky; top: 0; background: var(--bg); z-index: 5;
        }
        .topbar-inner {
            max-width: 880px; margin: 0 auto; padding: 12px 20px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .title { font-weight: 700; letter-spacing: .03em; }
        .topbar-actions { display: flex; align-items: center; gap: 8px; }

        .btn {
            background: transparent; color: var(--text);
            border: 1px solid var(--border-strong);
            padding: 6px 14px; font: inherit; font-size: 12px; cursor: pointer;
            text-transform: uppercase; letter-spacing: .06em;
        }
        .btn:hover { background: var(--surface-2); border-color: #4d4d4d; }
        .btn.btn-muted { color: var(--muted); border-color: var(--border); }
        .btn.btn-muted:hover { color: var(--text); border-color: var(--border-strong); }

        .wrap { max-width: 880px; margin: 0 auto; padding: 24px 20px 80px; }

        .flash { padding: 10px 14px; border: 1px solid var(--border); border-left-width: 3px; margin-bottom: 16px; font-size: 12px; background: var(--surface); }
        .flash.ok { border-left-color: #2ea043; color: #6ee787; }
        .flash.err { border-left-color: #da3633; color: #ff7b72; }
        .flash.warn { border-left-color: #555; color: var(--muted); }
        .flash code { color: var(--text); }

        .section-head {
            display: flex; align-items: baseline; justify-content: space-between;
            margin: 28px 0 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border);
        }
        .section-head h2 { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .12em; color: var(--muted); margin: 0; }
        .section-head .count { font-size: 11px; color: var(--dim); }

        .tabs { display: flex; gap: 0; margin-bottom: 0; border-bottom: 1px solid var(--border); }
        .tab-link {
            padding: 7px 16px; font: inherit; font-size: 11px; text-transform: uppercase;
            letter-spacing: .1em; color: var(--muted); border: 1px solid transparent;
            border-bottom: none; cursor: pointer; text-decoration: none;
            margin-bottom: -1px;
        }
        .tab-link:hover { color: var(--text); text-decoration: none; }
        .tab-link.active {
            color: var(--text); background: var(--bg);
            border-color: var(--border); border-bottom-color: var(--bg);
        }

        .rows { display: flex; flex-direction: column; }
        .row { border: 1px solid var(--border); border-top: none; padding: 12px 14px; background: var(--surface); }
        .rows .row:first-child { border-top: 1px solid var(--border); }
        .row .line { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .row .title { color: var(--text); font-weight: 600; }
        .row .sub { color: var(--muted); font-size: 12px; }
        .row .spacer { margin-left: auto; }
        .row .when { color: var(--dim); font-size: 12px; white-space: nowrap; }

        .tag {
            font-size: 10px; text-transform: uppercase; letter-spacing: .08em;
            padding: 2px 7px; border: 1px solid var(--border-strong); color: var(--muted);
        }
        .tag.sent { color: #3fb950; border-color: #2ea043; }
        .tag.failed { color: #f85149; border-color: #da3633; }
        .tag.skipped { color: var(--dim); border-color: var(--border); }
        .tag.available { color: #3fb950; border-color: #2ea043; }
        .tag.adopted { color: #f85149; border-color: #da3633; }

        .body { white-space: pre-wrap; word-break: break-word; color: var(--muted); font-size: 12px; margin-top: 8px; padding-left: 2px; border-left: 1px solid var(--border); padding: 8px 0 0 12px; }
        .err { color: #9a9a9a; font-size: 12px; margin-top: 6px; }
        .new { color: var(--text); font-size: 11px; text-transform: uppercase; letter-spacing: .08em; }

        .empty { color: var(--dim); font-size: 12px; padding: 18px 14px; border: 1px dashed var(--border); background: var(--surface); }

        .pager { display: flex; align-items: center; gap: 10px; margin-top: 12px; font-size: 12px; }
        .pg { border: 1px solid var(--border-strong); padding: 4px 12px; text-transform: uppercase; letter-spacing: .06em; color: var(--text); }
        .pg:hover { background: var(--surface-2); text-decoration: none; }
        .pg.disabled { color: var(--dim); border-color: var(--border); pointer-events: none; }
        .pg-info { color: var(--muted); }

        .notif-status { font-size: 11px; color: var(--dim); padding: 0 4px; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-inner">
            <span class="title">Cat Finder</span>
            <div class="topbar-actions">
                <form method="POST" action="{{ route('notifications.toggle') }}">
                    @csrf
                    @if ($notificationsEnabled)
                        <button class="btn" type="submit" title="Notifications on — click to disable">Notifs on</button>
                    @else
                        <button class="btn btn-muted" type="submit" title="Notifications off — click to enable">Notifs off</button>
                    @endif
                </form>
                <form method="POST" action="{{ route('check') }}">
                    @csrf
                    <button class="btn" type="submit">Run check</button>
                </form>
            </div>
        </div>
    </div>

    <div class="wrap">
        @if (session('status'))
            <div class="flash ok">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash err">{{ session('error') }}</div>
        @endif
        @unless ($twilioConfigured)
            <div class="flash warn">vonage not configured &mdash; set <code>VONAGE_API_KEY</code>, <code>VONAGE_API_SECRET</code>, <code>VONAGE_FROM</code>, <code>VONAGE_TO</code></div>
        @endunless

        <div class="section-head">
            <h2>Notifications</h2>
            <span class="count">{{ $logs->total() }} entries</span>
        </div>
        <div class="rows">
            @forelse ($logs as $log)
                <div class="row">
                    <div class="line">
                        <span class="tag {{ $log->status }}">{{ $log->status }}</span>
                        <span class="sub">{{ $log->animal_count }} animal(s){{ $log->recipient ? ' · '.$log->recipient : '' }}</span>
                        <span class="spacer"></span>
                        <span class="when">{{ $log->created_at?->format('y-m-d H:i') }}</span>
                    </div>
                    @if ($log->body)
                        <div class="body">{{ $log->body }}</div>
                    @endif
                    @if ($log->error)
                        <div class="err">! {{ $log->error }}</div>
                    @endif
                </div>
            @empty
                <div class="empty">no notifications yet — run a check to establish a baseline</div>
            @endforelse
        </div>
        @if ($logs->hasPages())
            <div class="pager">
                @if ($logs->onFirstPage())
                    <span class="pg disabled">prev</span>
                @else
                    <a class="pg" href="{{ $logs->previousPageUrl() }}">prev</a>
                @endif
                <span class="pg-info">page {{ $logs->currentPage() }} / {{ $logs->lastPage() }}</span>
                @if ($logs->hasMorePages())
                    <a class="pg" href="{{ $logs->nextPageUrl() }}">next</a>
                @else
                    <span class="pg disabled">next</span>
                @endif
            </div>
        @endif

        <div class="section-head" style="margin-bottom: 0;">
            <h2>Tracked</h2>
            <span class="count">{{ $availableCount }} available · {{ $adoptedCount }} adopted</span>
        </div>
        <div class="tabs">
            <a class="tab-link {{ $tab === 'available' ? 'active' : '' }}"
               href="{{ route('dashboard', ['tab' => 'available']) }}">
                Available ({{ $availableCount }})
            </a>
            <a class="tab-link {{ $tab === 'adopted' ? 'active' : '' }}"
               href="{{ route('dashboard', ['tab' => 'adopted']) }}">
                Adopted ({{ $adoptedCount }})
            </a>
        </div>
        <div class="rows">
            @forelse ($animals as $a)
                <div class="row">
                    <div class="line">
                        <a class="title" href="{{ $a->url }}" target="_blank" rel="noopener">{{ $a->name }}</a>
                        <span class="tag {{ $a->removed_at ? 'adopted' : 'available' }}">{{ $a->type }}</span>
                        @unless ($a->notified)<span class="new">&#9679; new</span>@endunless
                        <span class="spacer"></span>
                        <span class="sub">{{ collect([$a->age, $a->site])->filter()->implode(' · ') }}</span>
                    </div>
                </div>
            @empty
                <div class="empty">nothing tracked yet</div>
            @endforelse
        </div>
        @if ($animals->hasPages())
            <div class="pager">
                @if ($animals->onFirstPage())
                    <span class="pg disabled">prev</span>
                @else
                    <a class="pg" href="{{ $animals->previousPageUrl() }}">prev</a>
                @endif
                <span class="pg-info">page {{ $animals->currentPage() }} / {{ $animals->lastPage() }}</span>
                @if ($animals->hasMorePages())
                    <a class="pg" href="{{ $animals->nextPageUrl() }}">next</a>
                @else
                    <span class="pg disabled">next</span>
                @endif
            </div>
        @endif
    </div>
</body>
</html>
