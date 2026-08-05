@extends('backoffice.layout', ['title' => 'Nuova nota di credito'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture', 'href' => route('accounting.invoices.index')],
        'level_2' => ['label' => 'Nuova nota di credito'],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            @livewire('quick-invoice-wizard', [
                'documentType'      => $documentType,
                'parentInvoiceId'   => $parentInvoiceId,
                'parentExternalRef' => $parentExternalRef,
                'parentSummary'     => $parentSummary,
                'prefillCustomer'   => $prefillCustomer,
                'prefillLines'      => $prefillLines,
            ])
        </div>
    </div>
@endsection
