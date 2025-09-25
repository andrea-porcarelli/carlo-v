@if ($media->count() == 0)
    <div class="alert alert-info">Nessun elemento caricato</div>
@else
    <div class="row media-library">
        @foreach($media as $item)
            <div class="col-xs-6 col-sm-3 media-item">
                <b>{{ $item->type_label }}</b>
                @if ($item->mime_type !== 'application/pdf')
                    <img src="{{ $item->url }}">
                @endif
                <div class="media-options">

                </div>
            </div>
        @endforeach
    </div>
@endif
