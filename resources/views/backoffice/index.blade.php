@extends('backoffice.layout')

@section('breadcrumb')
    @include('backoffice.components.breadcrumb', [
        'title' => 'Dashboard ',
        'level_1' => ['label' => 'Dashboard'],
    ])
@endsection

@section('main-content')
    <div class="row">
    </div>
@endsection

@section('custom-script')

@endsection
