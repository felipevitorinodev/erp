<style>
    @if(!empty($pdf))
        @font-face {
            font-family: 'IBM Plex Sans';
            font-style: normal;
            font-weight: 400;
            src: url("{{ public_path('fonts/IBMPlexSans-Regular.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'IBM Plex Sans';
            font-style: normal;
            font-weight: 500;
            src: url("{{ public_path('fonts/IBMPlexSans-Medium.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'IBM Plex Sans';
            font-style: normal;
            font-weight: 600;
            src: url("{{ public_path('fonts/IBMPlexSans-SemiBold.ttf') }}") format('truetype');
        }
        @font-face {
            font-family: 'IBM Plex Sans';
            font-style: normal;
            font-weight: 700;
            src: url("{{ public_path('fonts/IBMPlexSans-Bold.ttf') }}") format('truetype');
        }
    @endif
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; }
    body {
        font-family: 'IBM Plex Sans', 'DejaVu Sans', sans-serif;
        color: #152232;
        font-size: 12px;
        line-height: 1.4;
    }
    .num { font-variant-numeric: tabular-nums; }

    .doc-accent { height: 6px; background: #E35A12; margin: 0 0 18px; }
    .doc-head { width: 100%; border-collapse: collapse; margin: 0 0 16px; }
    .doc-head td { vertical-align: middle; }
    .doc-brand { width: 58%; padding: 0 16px 14px 0; border-bottom: 1px solid #E0E6EE; }
    .doc-title { text-align: right; padding: 0 0 14px 16px; border-bottom: 1px solid #E0E6EE; }
    .co-logo-cell { width: 52px; vertical-align: middle; }
    .co-logo {
        width: 40px; height: 40px; background: #163A5F; border-radius: 10px;
        text-align: center; line-height: 40px; font-size: 13px; font-weight: 700; color: #fff;
    }
    .co-logo-img { height: 42px; width: auto; }
    .co-name { font-size: 15px; font-weight: 700; color: #163A5F; }
    .co-meta { margin-top: 2px; font-size: 11px; line-height: 1.45; color: #5E7084; }
    .doc-kind {
        display: inline-block; padding: 3px 8px; border-radius: 999px;
        background: #FDF0E8; color: #C2410C; font-size: 10px; font-weight: 700;
        letter-spacing: 0.06em; text-transform: uppercase;
    }
    .doc-name { margin-top: 6px; font-size: 18px; font-weight: 700; color: #152232; line-height: 1.2; }
    .doc-period { margin-top: 3px; font-size: 12px; color: #5E7084; }

    .filters { margin: 0 0 14px; }
    .chip {
        display: inline-block; margin: 0 6px 6px 0; padding: 4px 8px;
        background: #F4F7FB; border-radius: 6px; font-size: 11px; color: #5E7084;
    }
    .chip b { font-weight: 600; color: #152232; }

    .kpis { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin: 0 0 6px; }
    .kpi { width: 33%; background: #F8FAFC; border-top: 3px solid #163A5F; padding: 10px 12px; vertical-align: top; }
    .kpi-label { font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #6B7C90; }
    .kpi-value { margin-top: 4px; font-size: 18px; font-weight: 700; color: #163A5F; }
    .kpi-note { margin-top: 2px; font-size: 11px; color: #5E7084; }

    .sec-title { font-size: 13px; font-weight: 700; color: #163A5F; margin: 16px 0 8px; }

    .grid { width: 100%; border-collapse: collapse; font-size: 12px; }
    .grid th {
        padding: 7px 10px; text-align: left; font-size: 11px; font-weight: 600;
        color: #445468; background: #F1F5F9; border-bottom: 1px solid #E0E6EE;
    }
    .grid td { padding: 8px 10px; border-bottom: 1px solid #E0E6EE; vertical-align: middle; }
    .grid tbody tr:nth-child(even) td { background: #F6F8FB; }
    .grid .r, .grid th.r { text-align: right; }
    .grid .c, .grid th.c { text-align: center; }
    .grid .muted { color: #5E7084; }
    .grid tbody tr.total td { padding-top: 8px; font-weight: 700; color: #163A5F; background: #E8EEF5; border-bottom: none; }
    .grid thead { display: table-header-group; }
    .item-nome { font-weight: 600; }
    .item-codigo { display: block; margin-top: 2px; font-size: 11px; color: #5E7084; }

    .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; line-height: 1.35; }
    .pill.ok { color: #1F6F3B; background: #D4EDDA; }
    .pill.wait { color: #7A5200; background: #FFF3CD; }
    .pill.mute { color: #445468; background: #E8ECF1; }

    .bar { width: 96px; height: 6px; background: #E0E6EE; border-radius: 999px; }
    .bar-fill { height: 6px; background: #1F4F7A; border-radius: 999px; }
    .bar-pct { padding-left: 8px; white-space: nowrap; color: #445468; }

    .summary { width: 248px; margin-left: auto; border-collapse: collapse; margin-top: 16px; background: #F8FAFC; page-break-inside: avoid; }
    .summary td { padding: 4px 12px; font-size: 12px; color: #5E7084; }
    .summary tr:first-child td { padding-top: 10px; }
    .summary td.r { text-align: right; font-weight: 600; color: #152232; }
    .summary .grand td, .summary .grand td.r {
        background: #163A5F; color: #fff; border-top: none;
        padding: 9px 12px; font-size: 14px; font-weight: 700;
    }

    .fichas { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0 0 2px; }
    .ficha { width: 48%; background: #F8FAFC; border-left: 3px solid #163A5F; padding: 7px 12px 5px; vertical-align: top; page-break-inside: avoid; }
    .ficha-gap { width: 12px; padding: 0; border: none; background: #fff; }
    .ficha-titulo { font-size: 11px; font-weight: 700; color: #163A5F; margin-bottom: 3px; }
    .ficha p { font-size: 12px; line-height: 1.35; margin: 0 0 1px; font-weight: 600; color: #152232; }
    .ficha p span { display: inline; margin: 0 6px 0 0; font-size: 10px; font-weight: 600; letter-spacing: 0.03em; text-transform: uppercase; color: #7A8B9C; }
    .ficha p .pill { display: inline-block; margin: 0; letter-spacing: 0; text-transform: none; font-size: 11px; vertical-align: middle; }

    .nota { background: #FDF0E8; border-left: 3px solid #E35A12; padding: 10px 12px; font-size: 12px; margin-top: 12px; page-break-inside: avoid; }
    .nota strong { display: block; font-size: 10px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #C2410C; margin-bottom: 3px; }

    .sign { width: 100%; margin-top: 40px; border-collapse: collapse; page-break-inside: avoid; }
    .sign td { width: 46%; vertical-align: top; }
    .sign .sign-gap { width: 8%; }
    .sign-line { border-top: 1px solid #C5CED8; padding-top: 8px; font-size: 11px; color: #6B7C90; }

    .doc-foot { width: 100%; border-collapse: collapse; margin-top: 26px; border-top: 1px solid #E0E6EE; }
    .doc-foot td { padding-top: 8px; font-size: 10px; color: #8A97A8; }
    .doc-foot .r { text-align: right; }

    .cupom { width: 302px; padding: 16px 18px 20px; background: #fff; color: #152232; font-family: 'DejaVu Sans Mono', ui-monospace, 'Courier New', monospace; font-size: 12px; line-height: 1.35; }
    .c-head { text-align: center; }
    .c-name { font-size: 15px; font-weight: 700; color: #163A5F; }
    .c-meta { margin-top: 2px; font-size: 11px; color: #5E7084; }
    .c-dash { margin: 10px 0; border-top: 1px dashed #9AA6B5; height: 0; }
    .c-title { text-align: center; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; }
    .c-sub { text-align: center; margin-top: 2px; font-size: 11px; color: #5E7084; }
    .c-item { width: 100%; border-collapse: collapse; margin: 0; }
    .c-item td { padding: 1px 0; vertical-align: top; }
    .c-item .n { font-weight: 600; }
    .c-item .v { font-weight: 600; text-align: right; white-space: nowrap; padding-left: 8px; }
    .c-item .d { font-size: 11px; color: #5E7084; padding-bottom: 5px; }
    .c-kv { width: 100%; border-collapse: collapse; }
    .c-kv td { padding: 2px 0; }
    .c-kv td:first-child { white-space: nowrap; padding-right: 12px; }
    .c-kv .v { text-align: right; padding-left: 8px; }
    .c-kv.adj td { color: #5E7084; }
    .c-total { width: 100%; border-collapse: collapse; }
    .c-total td { padding: 6px 0 4px; font-size: 18px; font-weight: 700; color: #163A5F; }
    .c-total .v { text-align: right; }
    .c-paid { text-align: center; margin-top: 8px; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; }
    .c-thanks { text-align: center; margin-top: 12px; font-size: 12px; color: #152232; }
    .c-small { text-align: center; margin-top: 4px; font-size: 11px; color: #5E7084; }
</style>
