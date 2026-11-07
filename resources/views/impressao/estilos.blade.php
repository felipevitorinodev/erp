<style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; }
    body {
        font-family: {{ !empty($pdf) ? "'DejaVu Sans', sans-serif" : "'IBM Plex Sans', 'DejaVu Sans', sans-serif" }};
        color: #152232;
        font-size: 12px;
    }
    .num { font-variant-numeric: tabular-nums; }

    .doc-head { width: 100%; border-collapse: collapse; border-bottom: 2px solid #163A5F; margin-bottom: 16px; }
    .doc-head td { vertical-align: top; padding-bottom: 14px; }
    .co-logo-cell { width: 60px; vertical-align: middle; }
    .co-logo {
        width: 48px; height: 48px; border: 1px dashed #9AA8B8; border-radius: 10px;
        text-align: center; line-height: 48px; font-size: 10px; font-weight: 600; color: #5E7084;
    }
    .co-logo-img { height: 48px; width: auto; }
    .co-name { font-size: 16px; font-weight: 700; color: #163A5F; }
    .co-meta { margin-top: 3px; font-size: 12px; line-height: 1.45; color: #5E7084; }
    .doc-title { text-align: right; }
    .doc-kind { font-size: 11px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #C2410C; }
    .doc-name { margin-top: 4px; font-size: 22px; font-weight: 700; color: #163A5F; }
    .doc-period { margin-top: 4px; font-size: 12px; color: #5E7084; }

    .filters { margin: 0 0 14px; }
    .chip {
        display: inline-block; margin: 0 8px 8px 0; padding: 6px 10px;
        border: 1px solid #D9E1EA; border-radius: 999px; font-size: 12px; color: #5E7084;
    }
    .chip b { font-weight: 600; color: #152232; }

    .kpis { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin: 0 0 8px; }
    .kpi { width: 33%; border: 1px solid #D9E1EA; border-radius: 10px; padding: 12px 14px; vertical-align: top; background: #fff; }
    .kpi-label { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #5E7084; }
    .kpi-value { margin-top: 6px; font-size: 20px; font-weight: 700; color: #163A5F; }
    .kpi-note { margin-top: 2px; font-size: 12px; color: #5E7084; }

    .sec-title { font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #163A5F; margin: 16px 0 8px; }

    .grid { width: 100%; border-collapse: collapse; font-size: 12px; }
    .grid th {
        padding: 8px 10px; text-align: left; font-size: 11px; font-weight: 700;
        letter-spacing: 0.04em; text-transform: uppercase; color: #163A5F;
        background: #EAF2FA; border-bottom: 1px solid #163A5F;
    }
    .grid td { padding: 8px 10px; border-bottom: 1px solid #D9E1EA; vertical-align: middle; }
    .grid .r, .grid th.r { text-align: right; }
    .grid .c, .grid th.c { text-align: center; }
    .grid .muted { color: #5E7084; }
    .grid tr.total td { padding-top: 10px; font-weight: 700; border-top: 1px solid #163A5F; border-bottom: none; }
    .grid thead { display: table-header-group; }
    .item-nome { font-weight: 600; }
    .item-codigo { display: block; margin-top: 2px; font-size: 11px; color: #5E7084; }

    .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; border: 1px solid; font-size: 11px; font-weight: 600; }
    .pill.ok { color: #0E6B4D; border-color: #9ED9C2; background: #F1FBF7; }
    .pill.wait { color: #9A3412; border-color: #F3C29D; background: #FFF8F2; }
    .pill.mute { color: #5E7084; border-color: #D9E1EA; background: #fff; }

    .bar { width: 96px; height: 6px; background: #EAF2FA; border-radius: 999px; }
    .bar-fill { height: 6px; background: #1F4F7A; }
    .bar-pct { padding-left: 8px; white-space: nowrap; }

    .summary { width: 280px; margin-left: auto; border-collapse: collapse; margin-top: 14px; }
    .summary td { padding: 3px 0; font-size: 13px; color: #5E7084; }
    .summary td.r { text-align: right; font-weight: 600; color: #152232; }
    .summary .grand td { border-top: 2px solid #163A5F; padding-top: 10px; font-size: 16px; font-weight: 700; color: #163A5F; }

    .fichas { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin: 4px 0 8px; }
    .ficha { width: 48%; border: 1px solid #D9E1EA; border-radius: 10px; padding: 12px 14px; vertical-align: top; }
    .ficha-gap { width: 12px; padding: 0; border: none; }
    .ficha-titulo { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #163A5F; margin-bottom: 8px; }
    .ficha p { font-size: 12px; line-height: 1.45; margin: 0 0 3px; }
    .ficha p span { color: #5E7084; margin-right: 6px; }

    .nota { border: 1px solid #D9E1EA; border-radius: 10px; padding: 12px 14px; font-size: 12px; margin-top: 12px; }
    .nota strong { display: block; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #163A5F; margin-bottom: 4px; }

    .sign { width: 100%; margin-top: 36px; border-collapse: collapse; }
    .sign td { width: 46%; vertical-align: top; }
    .sign .sign-gap { width: 8%; }
    .sign-line { border-top: 1px solid #152232; padding-top: 6px; font-size: 12px; color: #5E7084; }

    .doc-foot { width: 100%; border-collapse: collapse; margin-top: 22px; border-top: 1px solid #D9E1EA; }
    .doc-foot td { padding-top: 8px; font-size: 11px; color: #5E7084; }
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
