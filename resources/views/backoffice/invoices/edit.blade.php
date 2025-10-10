@extends('backoffice.layout', ['title' => 'Dettaglio fattura: ' . $object->invoice_number])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture', 'href' => route('invoices.index')],
        'level_2' => ['label' => 'Dettaglio fattura: ' . $object->invoice_number],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-xs-12">
            <div class="row">
                <div class="invoice-wrapper">
                    <div class="invoice-container">
                        <!-- Header -->
                        <div class="invoice-header">
                            <div class="invoice-meta">
                                <div>
                                    <h1>FATTURA</h1>
                                    <div class="invoice-number">
                                        <i class="fas fa-file-invoice"></i> N. {{ $object->invoice_number }} | {{ Utils::data_text($object->invoice_date) }}
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn btn-print" onclick="window.print()">
                                        <i class="fas fa-print"></i> Stampa
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="invoice-body">
                            <!-- Info Cards -->
                            <div class="info-section">
                                <div class="info-card">
                                    <div class="info-card-header">
                                        <div class="info-icon">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <h5>Fornitore</h5>
                                    </div>
                                    <div class="info-card-body">
                                        <div class="company-name">{{ $object->supplier->company_name }}</div>
                                        <p>
                                            {{ $object->supplier->address }}, {{ $object->supplier->number }}<br>
                                            {{ $object->supplier->zip_code }} {{ $object->supplier->city }} ({{ $object->supplier->province }})
                                        </p>
                                        <p><strong>P.IVA:</strong> {{ $object->supplier->vat_number }}</p>
                                        @isset($object->supplier->phone)
                                        <p><strong>Tel:</strong> {{ $object->supplier->phone }}</p>
                                        @endisset
                                        @isset($object->supplier->email)
                                        <p><strong>Email:</strong> {{ $object->supplier->email }}</p>
                                        @endisset
                                    </div>
                                </div>

                            </div>

                            <!-- Tabella Prodotti -->
                            <div class="section-title">
                                <h5><i class="fas fa-box"></i> Prodotti e Servizi</h5>
                            </div>

                            <table class="table table-products mb-0">
                                <thead>
                                <tr>
                                    <th style="width: 5%">#</th>
                                    <th style="width: 40%">Descrizione</th>
                                    <th style="width: 10%" class="text-center">Qtà</th>
                                    <th style="width: 15%" class="text-right">Prezzo Unit.</th>
                                    <th style="width: 10%" class="text-center">IVA</th>
                                    <th style="width: 20%" class="text-right">Totale</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($object->products as $k => $product)
                                    <tr>
                                        <td class="row-number">{{ $k +1 }}</td>
                                        <td>
                                            <div class="product-description">{{ $product->product_name }}</div>
                                        </td>
                                        <td class="text-center">{{ $product->quantity }}</td>
                                        <td class="text-right">{{ Utils::price($product->price) }}</td>
                                        <td class="text-center">{{ $product->iva }}%</td>
                                        <td class="text-right"><strong>{{ $product->price * $product->quantity }}</strong></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>

                            <!-- Riepilogo -->
                            <div class="summary-section">
                                <div class="row">
                                    <div class="col-md-7"></div>
                                    <div class="col-md-5">
                                        <div class="summary-box">
                                            <h5><i class="fas fa-calculator"></i> Riepilogo IVA</h5>

                                            <table class="table table-sm iva-table mb-0">
                                                <thead>
                                                <tr>
                                                    <th>Aliquota</th>
                                                    <th class="text-right">Imponibile</th>
                                                    <th class="text-right">Imposta</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach($object->riepilogo_iva() as $iva => $row)
                                                    <tr>
                                                        <td>{{ $iva }}%</td>
                                                        <td class="text-right">{{ Utils::price($row['imponibile']) }}</td>
                                                        <td class="text-right">{{ Utils::price($row['imposta']) }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                            </table>

                                            <hr class="summary-divider">

                                            <div class="summary-row">
                                                <strong>Totale Imponibile</strong>
                                                <span>{{ Utils::price($object->riepilogo_iva()->sum('imponibile')) }}</span>
                                            </div>
                                            <div class="summary-row">
                                                <strong>Totale IVA</strong>
                                                <span>{{ Utils::price($object->riepilogo_iva()->sum('imposta')) }}</span>
                                            </div>

                                            <hr class="summary-divider">

                                            <div class="total-row text-right">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <strong>TOTALE FATTURA</strong><br />
                                                    <span class="total-amount">{{ Utils::price($object->riepilogo_iva()->sum('imposta') + $object->riepilogo_iva()->sum('imponibile')) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('custom-css')
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap');


        .invoice-wrapper {
            max-width: 1200px;
            margin: 0 auto;
        }

        .invoice-container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        .invoice-header {
            background: #2f4050;
            color: white;
            padding: 40px;
        }

        .invoice-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0 0 10px 0;
            letter-spacing: -0.5px;
        }

        .invoice-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .invoice-number {
            font-size: 1.1rem;
            font-weight: 500;
            opacity: 0.95;
        }

        .action-buttons .btn {
            border-radius: 8px;
            padding: 10px 24px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-print {
            background: rgba(255,255,255,0.2);
            color: white;
            backdrop-filter: blur(10px);
        }

        .btn-print:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
            color: white;
        }

        .btn-download {
            background: #1ab394;
            color: white;
        }

        .btn-download:hover {
            background: #18a689;
            transform: translateY(-2px);
            color: white;
        }

        .invoice-body {
            padding: 40px;
        }

        .info-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .info-card {
            background: #ffffff;
            border: 1px solid #e7eaec;
            border-radius: 2px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .info-card-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e7eaec;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            background: #1ab394;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin-right: 12px;
            font-size: 16px;
        }

        .info-card-header h5 {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .info-card-body p {
            margin: 8px 0;
            font-size: 0.95rem;
            color: #334155;
            line-height: 1.6;
        }

        .info-card-body strong {
            color: #1e293b;
            font-weight: 600;
        }

        .company-name {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 12px;
        }

        .details-grid {
            background: #ffffff;
            border: 1px solid #e7eaec;
            border-radius: 2px;
            padding: 24px;
            margin-bottom: 40px;
        }

        .details-grid h5 {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 20px;
        }

        .details-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .detail-item strong {
            color: #64748b;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .detail-item span {
            color: #1e293b;
            font-size: 1rem;
            font-weight: 500;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge-warning {
            background: #fbbf24;
            color: #78350f;
        }

        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e7eaec;
        }

        .section-title h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
        }

        .table-products {
            border-radius: 2px;
            overflow: hidden;
            box-shadow: none;
            border: 1px solid #e7eaec;
        }

        .table-products thead {
            background: #2f4050;
        }

        .table-products thead th {
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 16px;
            border: none;
        }

        .table-products tbody tr {
            transition: all 0.2s ease;
        }

        .table-products tbody tr:hover {
            background-color: #f9f9f9;
        }

        .table-products tbody td {
            padding: 16px;
            vertical-align: middle;
            border-color: #e7eaec;
            color: #676a6c;
        }

        .product-description {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .product-details {
            font-size: 0.85rem;
            color: #64748b;
        }

        .row-number {
            font-weight: 700;
            color: #94a3b8;
            font-size: 1rem;
        }

        .summary-section {
            margin-top: 40px;
        }

        .summary-box {
            background: #ffffff;
            border: 1px solid #e7eaec;
            border-radius: 2px;
            padding: 28px;
        }

        .summary-box h5 {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 20px;
        }

        .iva-table {
            background: #f9f9f9;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 20px;
            border: 1px solid #e7eaec;
        }

        .iva-table thead th {
            background: #2f4050;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
            padding: 12px;
            border: none;
        }

        .iva-table tbody td {
            padding: 12px;
            border-color: #e2e8f0;
            color: #334155;
            font-weight: 500;
        }

        .summary-divider {
            border: none;
            border-top: 2px solid #cbd5e1;
            margin: 10px 0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 1rem;
        }

        .summary-row strong {
            color: #1e293b;
            font-weight: 600;
        }

        .summary-row span {
            color: #334155;
            font-weight: 500;
        }

        .total-row {
            background: white;
            border-radius: 8px;
            padding: 10px;
        }

        .total-row strong {
            font-size: 1.1rem;
            color: #1e293b;
        }

        .total-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #1ab394;
        }

        .notes-box {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 24px;
            margin-top: 40px;
        }

        .notes-box h5 {
            display: flex;
            align-items: center;
            font-size: 1rem;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 12px;
        }

        .notes-box h5 i {
            margin-right: 10px;
            color: #f59e0b;
        }

        .notes-box p {
            margin: 0;
            color: #78350f;
            line-height: 1.7;
            font-size: 0.95rem;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .invoice-container {
                box-shadow: none;
            }
            .action-buttons {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .invoice-header {
                padding: 24px;
            }
            .invoice-header h1 {
                font-size: 1.8rem;
            }
            .invoice-body {
                padding: 24px;
            }
            .table-products {
                font-size: 0.85rem;
            }
        }
    </style>
@endsection
