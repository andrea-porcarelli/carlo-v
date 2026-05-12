@extends('backoffice.layout', ['title' => 'Nuova fattura'])

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'level_1' => ['label' => 'Fatture', 'href' => route('accounting.invoices.index')],
        'level_2' => ['label' => 'Nuova fattura'],
    ])
@endsection

@section('main-content')
    <div class="row">
        <div class="col-lg-12">
            @livewire('quick-invoice-wizard')
        </div>
    </div>
@endsection
