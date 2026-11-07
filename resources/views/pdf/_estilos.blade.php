    <style>
        @font-face {
            font-family: 'DejaVuSans';
            src: local('DejaVu Sans'), local('DejaVuSans');
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            font-family: 'DejaVuSans', Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1E293B;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
        }

        .page {
            padding: 28px 32px 24px;
        }

        /* â”€â”€ TOPO â”€â”€ */
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 3px solid #1C2B42;
        }

        .header td {
            vertical-align: middle;
        }

        .logo {
            height: 44px;
            width: auto;
        }

        .brand-sub {
            font-size: 9px;
            color: #64748B;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .doc-badge {
            text-align: right;
        }

        .doc-badge .label {
            display: inline-block;
            background: #1C2B42;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 3px;
        }

        .doc-badge .numero {
            display: block;
            font-size: 20px;
            font-weight: bold;
            color: #1C2B42;
            margin-top: 5px;
        }

        .doc-badge .data {
            font-size: 10px;
            color: #64748B;
            margin-top: 2px;
        }

        /* â”€â”€ STATUS STRIP â”€â”€ */
        .status-strip {
            width: 100%;
            background: #E7F5EE;
            border: 1px solid #B2DFC4;
            border-radius: 4px;
            padding: 7px 14px;
            margin-bottom: 20px;
            font-size: 10px;
            color: #1A6B3C;
        }

        .status-strip strong {
            font-size: 11px;
        }

        /* â”€â”€ INFO BOXES â”€â”€ */
        .info-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }

        .info-box {
            width: 48%;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 12px 14px;
            vertical-align: top;
            background: #F8FAFC;
        }

        .info-box-gap {
            width: 4%;
        }

        .info-box-title {
            font-size: 9px;
            font-weight: bold;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding-bottom: 7px;
            margin-bottom: 7px;
            border-bottom: 1px solid #E2E8F0;
        }

        .info-line {
            margin-bottom: 5px;
            line-height: 1.45;
            color: #334155;
        }

        .info-line strong {
            color: #1E293B;
        }

        /* â”€â”€ SECTION HEADING â”€â”€ */
        .section-heading {
            font-size: 9px;
            font-weight: bold;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 6px;
            padding-left: 2px;
        }

        /* â”€â”€ ITEMS TABLE â”€â”€ */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        table.items thead th {
            background: #1C2B42;
            color: #ffffff;
            padding: 9px 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        table.items thead th:first-child {
            border-radius: 3px 0 0 0;
        }

        table.items thead th:last-child {
            border-radius: 0 3px 0 0;
        }

        table.items tbody td {
            padding: 9px 10px;
            border-bottom: 1px solid #E2E8F0;
            vertical-align: middle;
            color: #334155;
        }

        table.items tbody tr:last-child td {
            border-bottom: none;
        }

        table.items tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }

        table.items tfoot td {
            padding: 0;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .item-nome {
            font-weight: bold;
            color: #1E293B;
        }

        /* â”€â”€ TOTALS â”€â”€ */
        .totals-wrap {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .totals-box {
            width: 280px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            background: #F8FAFC;
            padding: 14px 16px;
            margin-left: auto;
        }

        .totals-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-inner td {
            padding: 4px 0;
            font-size: 11px;
            color: #475569;
        }

        .totals-inner tr.divider td {
            border-top: 1px solid #CBD5E1;
            padding-top: 8px;
            margin-top: 4px;
        }

        .totals-inner tr.grand-total td {
            font-size: 15px;
            font-weight: bold;
            color: #1C2B42;
            padding-top: 8px;
        }

        .totals-inner .accent {
            color: #E66A35;
        }

        /* â”€â”€ OBSERVAÃ‡Ã•ES â”€â”€ */
        .obs-box {
            margin-top: 22px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 10px 14px;
            background: #FFFBF7;
            font-size: 10px;
            color: #64748B;
            line-height: 1.5;
        }

        .obs-box strong {
            display: block;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94A3B8;
            margin-bottom: 4px;
        }

        /* â”€â”€ ASSINATURAS â”€â”€ */
        .signatures {
            margin-top: 52px;
            width: 100%;
            border-collapse: collapse;
        }

        .signatures td {
            width: 40%;
            text-align: center;
            vertical-align: bottom;
        }

        .signatures .gap {
            width: 20%;
        }

        .sig-line {
            border-top: 1px solid #94A3B8;
            padding-top: 7px;
            font-size: 10px;
            color: #64748B;
        }

        .sig-line strong {
            display: block;
            color: #1E293B;
            font-size: 10px;
        }

        /* â”€â”€ FOOTER â”€â”€ */
        .footer {
            margin-top: 36px;
            padding-top: 10px;
            border-top: 1px solid #E2E8F0;
            font-size: 9px;
            color: #94A3B8;
            text-align: center;
            line-height: 1.6;
        }

        .footer .dot {
            margin: 0 4px;
        }

        .status-strip--espera {
            background: #FFF8F0;
            border-color: #FDDCB5;
            color: #92400E;
        }

        .status-strip--erro {
            background: #FDECEC;
            border-color: #F5C2C2;
            color: #8B1A1A;
        }

        .item-codigo {
            display: block;
            font-size: 9px;
            font-weight: normal;
            color: #64748B;
            margin-top: 2px;
        }

        .totals-box--wide {
            width: 320px;
        }
    </style>
