<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Server error · {{ config('app.name', 'Anzeigen Generator') }}</title>
    <style>
        :root {
            color-scheme: light dark;
            font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #fafafa;
            color: #18181b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background:
                radial-gradient(circle at top, rgba(244, 63, 94, .08), transparent 34rem),
                #fafafa;
        }

        main {
            width: min(100%, 38rem);
            padding: clamp(1.5rem, 5vw, 3rem);
            border: 1px solid #e4e4e7;
            border-radius: 1rem;
            background: rgba(255, 255, 255, .94);
            box-shadow: 0 24px 70px rgba(24, 24, 27, .10);
        }

        .mark {
            width: 3rem;
            height: 3rem;
            display: grid;
            place-items: center;
            border-radius: .75rem;
            background: #ffe4e6;
            color: #be123c;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .eyebrow {
            margin: 1.5rem 0 .5rem;
            color: #be123c;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.75rem, 5vw, 2.5rem);
            line-height: 1.12;
            letter-spacing: -.035em;
        }

        p {
            margin: 1rem 0 0;
            color: #52525b;
            line-height: 1.65;
        }

        .hint {
            padding: .875rem 1rem;
            border-radius: .75rem;
            background: #f4f4f5;
            font-size: .9rem;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1.5rem;
        }

        a {
            min-height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .7rem 1rem;
            border: 1px solid #d4d4d8;
            border-radius: .6rem;
            color: #18181b;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
        }

        a.primary {
            border-color: #18181b;
            background: #18181b;
            color: #fff;
        }

        a:focus-visible {
            outline: 3px solid rgba(225, 29, 72, .28);
            outline-offset: 3px;
        }

        @media (prefers-color-scheme: dark) {
            :root,
            body {
                background: #09090b;
                color: #fafafa;
            }

            body {
                background: radial-gradient(circle at top, rgba(244, 63, 94, .13), transparent 34rem), #09090b;
            }

            main {
                border-color: #27272a;
                background: rgba(24, 24, 27, .94);
                box-shadow: 0 24px 70px rgba(0, 0, 0, .45);
            }

            .mark {
                background: #4c0519;
                color: #fda4af;
            }

            .eyebrow {
                color: #fda4af;
            }

            p {
                color: #a1a1aa;
            }

            .hint {
                background: #27272a;
            }

            a {
                border-color: #52525b;
                color: #fafafa;
            }

            a.primary {
                border-color: #fafafa;
                background: #fafafa;
                color: #18181b;
            }
        }
    </style>
</head>
<body>
    <main>
        <div class="mark" aria-hidden="true">!</div>
        <p class="eyebrow">Error 500</p>
        <h1>Something went wrong on the server</h1>
        <p>
            The server could not complete this request. This is an internal error, not a missing page,
            and it may only be temporary.
        </p>
        <p class="hint">
            Your last action may not have been completed. Please try once more. If the problem continues,
            send a short report and mention what you clicked before this page appeared.
        </p>
        <div class="actions">
            <a class="primary" href="{{ route('home') }}">Return to the app</a>
            <a href="{{ route('support.create') }}">Report this problem</a>
        </div>
    </main>
</body>
</html>
