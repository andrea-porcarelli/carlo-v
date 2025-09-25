<div class="row">
    <div class="col-xs-{{ $col ?? '6' }}">
        <form action="{{ route('upload') }}" enctype="multipart/form-data" class="dropzone dropzone-simple {{ $class ?? ''}}" @isset($height) style="min-height: {{ $height }} !important;"  @endisset>
            @if(!isset($hide_message))
            <div class="dz-message" data-dz-message><span>Seleziona il file dal tuo computer</span></div>
            @else
                <div class="dz-message" data-dz-message></div>
            @endif
            <input type="hidden" name="name" value="{{ $name }}">
            <input type="hidden" name="path" value="{{ $path ?? '' }}">
            @if (isset($fill))
                <input type="hidden" name="fill" value="1">
            @endif
            @if (isset($type))
                <input type="hidden" name="type" value="{{ $type }}">
            @endif
            {{ csrf_field() }}
        </form>
    </div>
    <div class="col-xs-6">
        @if(isset($value))
            @if (!isset($type))
                <div class="upload-preview">
                    <img src="{{ asset('storage/' . $value) }}" class="">
                </div>
            @else
                @if ($type == 'file')
                    <a href="{{ asset('storage/' . $path . '/' . $value) }}"><b>APRI IL FILE CARICATO</b></a>
                @elseif($type == 'image')
                    <div class="upload-preview">
                        <img src="{{ asset('storage/' . $value) }}" class="">
                    </div>
             @endif
            @endif

        @endif
    </div>
</div>
