@props([
    'title' => null,
    'maxsize' => null,
    'media' => null,
    'file_path' => null,
    'file_type' => 'img',
    'entity_type' => null,
    'entity_id' => null,
    'class_upload' => null,
])

<div class="upload-file">
    <div class="row">
        <div class="col-xs-12 m-t-sm">
            <label>{{ $title ?? 'Carica immagine' }}</label>
            <form action="{{ route('upload') }}" enctype="multipart/form-data" class="dropzone {{ $class_upload }}">
                <div class="dz-message" data-dz-message><span>Seleziona il file dal tuo computer</span></div>
                <input type="hidden" name="path" value="{{ $file_path }}">
                <input type="hidden" name="type" value="{{ $file_type }}">
                <input type="hidden" name="maxsize" value="{{ $maxsize }}">
                <input type="hidden" name="entity_type" value="{{ $entity_type }}">
                <input type="hidden" name="entity_id" value="{{ $entity_id }}">
                {{ csrf_field() }}
                @if (isset($maxsize))
                    <small>Grandezza massima per ogni file: <b>{{ $maxsize }}Mb</b></small>
                @endif
            </form>
        </div>
        <div class="col-xs-12 m-t-sm">
            @if(isset($media))
                <ul class="upload-media">
                    @foreach($media as $item)
                        <li class="media_{{ $item->id }}">
                            <b>{{ $item->type_label }}</b>
                            <span>
                                <a href="{{ asset('storage/' . $path . '/' . $item->filename) }}" target="_blank">
                                    <button class="btn btn-xs btn-info" data-id="{{ $item->id }}" title="Mostra">
                                        <span class="fa fa-eye"></span>
                                    </button>
                                </a>
                                <button class="btn btn-xs btn-danger btn-delete-media" data-id="{{ $item->id }}" title="Elimina ">
                                    <span class="fa fa-times"></span>
                                </button>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
