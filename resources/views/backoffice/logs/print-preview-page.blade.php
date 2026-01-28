<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anteprima Stampa - {{ $printLog->getPrintTypeLabel() }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            background: #f5f5f5;
            padding: 20px;
        }

        .print-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 4px;
        }

        .print-info {
            background: #e9ecef;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-family: Arial, sans-serif;
        }

        .print-info h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .print-info p {
            margin: 5px 0;
            font-size: 14px;
            color: #666;
        }

        .print-info .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-primary { background: #007bff; color: white; }
        .badge-info { background: #17a2b8; color: white; }

        .print-receipt {
            font-size: 14px;
            line-height: 1.4;
        }

        .print-header {
            text-align: center;
            margin-bottom: 15px;
        }

        .printer-label {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .table-info {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }

        .table-info-large {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin: 15px 0;
        }

        .datetime {
            font-size: 12px;
            color: #666;
        }

        .operator {
            font-size: 12px;
            color: #666;
        }

        .operation {
            font-weight: bold;
            margin: 10px 0;
            font-size: 16px;
        }

        .print-separator {
            text-align: center;
            color: #999;
            margin: 10px 0;
            font-size: 12px;
        }

        .print-items {
            margin: 15px 0;
        }

        .item {
            margin-bottom: 10px;
        }

        .item-name {
            font-size: 16px;
        }

        .item-notes, .item-extra, .item-removal {
            font-size: 12px;
            color: #666;
            padding-left: 10px;
        }

        .item-extra {
            color: #28a745;
        }

        .item-removal {
            color: #dc3545;
        }

        .item-segue {
            text-align: center;
            font-weight: bold;
            color: #dc3545;
            margin: 10px 0;
        }

        /* Preconto styles */
        .preconto-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .covers-info {
            font-size: 14px;
            margin: 5px 0;
        }

        .preconto-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dotted #ddd;
        }

        .item-desc {
            flex: 1;
        }

        .item-price {
            font-weight: bold;
            text-align: right;
            min-width: 80px;
        }

        .print-total {
            text-align: right;
            font-size: 18px;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
        }

        .print-split {
            text-align: center;
            margin: 15px 0;
        }

        .split-separator {
            color: #999;
            margin: 5px 0;
        }

        .split-title {
            font-size: 18px;
            font-weight: bold;
            margin: 10px 0;
        }

        .split-amount {
            font-size: 16px;
        }

        .print-footer-note {
            text-align: center;
            font-size: 12px;
            color: #999;
            margin-top: 15px;
        }

        /* Marcia styles */
        .print-marcia .marcia-title {
            text-align: center;
            font-size: 48px;
            font-weight: bold;
            margin: 20px 0;
            line-height: 1.2;
        }

        .print-footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 15px;
        }

        /* Print button */
        .print-actions {
            text-align: center;
            margin-bottom: 20px;
            font-family: Arial, sans-serif;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            margin: 0 5px;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        @media print {
            .print-info, .print-actions {
                display: none;
            }

            body {
                background: white;
                padding: 0;
            }

            .print-container {
                box-shadow: none;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="print-actions">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Stampa
            </button>
            <button class="btn btn-secondary" onclick="window.close()">
                Chiudi
            </button>
        </div>

        <div class="print-info">
            <h4>Informazioni Stampa</h4>
            <p><strong>Data:</strong> {{ $printLog->created_at->format('d/m/Y H:i:s') }}</p>
            <p><strong>Tipo:</strong> {{ $printLog->getPrintTypeLabel() }}
                @if($printLog->operation)
                    - {{ $printLog->getOperationLabel() }}
                @endif
            </p>
            <p><strong>Stampante:</strong> {{ $printLog->printer->label ?? 'N/D' }} ({{ $printLog->printer->ip ?? 'N/D' }})</p>
            <p><strong>Operatore:</strong> {{ $printLog->user->name ?? 'N/D' }}</p>
            <p><strong>Stato:</strong>
                @if($printLog->success)
                    <span class="badge badge-success">OK</span>
                @else
                    <span class="badge badge-danger">Errore</span>
                @endif
            </p>
            @if($printLog->error_message)
                <p><strong>Errore:</strong> {{ $printLog->error_message }}</p>
            @endif
        </div>

        @if($printLog->print_content)
            {!! $printLog->print_content !!}
        @else
            <div style="text-align: center; padding: 40px; color: #999;">
                <p>Anteprima non disponibile</p>
            </div>
        @endif
    </div>
</body>
</html>
