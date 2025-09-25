
<div class="row">
    <div class="col-xs-12">
        <h4>Associa le bolle di consegna alla fattura {{ $invoice->invoice_number }}</h4>
    </div>
    <div class="col-xs-12">
        <input type="text" class="form-control search-delivery-note" placeholder="Cerca bolla">
    </div>
    <div class="col-xs-8 col-xs-offset-2 delivery-notes-list">
        @foreach($delivery_notes as $delivery_note)
            <div class=" delivery-note-invoice @if (in_array($delivery_note->id, $invoice_delivery_notes)) checked @endif" data-invoice-id="{{ $invoice->id }}" data-delivery-note-id="{{ $delivery_note->id }}">
                #{{ $delivery_note->delivery_code }} | Fattura {{ $delivery_note->invoice_code }}<br />
                <small>Registrato il {{ Utils::data_long($delivery_note->delivery_at) }} | Pezzi {{ $delivery_note->pieces }}</small>
                @if (in_array($delivery_note->id, $invoice_delivery_notes)) <i class="fa fa-check checkmark"></i>@endif
            </div>
        @endforeach
    </div>
</div>
