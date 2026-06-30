@extends('backoffice.layout', ['title' => 'Modifica fattura'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture', 'href' => route('accounting.invoices.index')],
        'level_2' => ['label' => 'Modifica fattura ' . $invoice->invoice_code],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            @livewire('quick-invoice-wizard', ['invoiceId' => $invoice->id])
        </div>
    </div>
@endsection
