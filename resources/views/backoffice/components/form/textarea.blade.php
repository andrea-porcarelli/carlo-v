@if(!isset($field))
    <div class="col-xs-12 col-sm-<?=$col ?? '4'?> m-t-sm @if(isset($searching)) position-relative @endif">
        <label>
            {{ $label ?? '' }}
        </label>
        @if($errors->has($name))
            <small class="text-danger">{{ $errors->first($name) }}</small>
        @endif
@endif
        <textarea
            class="form-control {{ $class ?? '' }}"
            name="{{ $name }}"
            id="{{ $id_input ?? $name }}"
            @if(isset($disabled))
                disabled
            @endif
            @if(isset($readonly) && $readonly)
                readonly
            @endif
            @if(isset($row))
                rows="{{ $row }}"
            @endif
            @if(isset($form))
                form="{{ $form }}"
            @endif
            @if(isset($placeholder))
                placeholder="{{ $placeholder }}"
            @endif
        >@if(isset($value)){{ $value ?? '' }}@else{{ $object[$name] ?? '' }}@endif</textarea>
@if(!isset($field))
        <div class="invalid-feedback {{ $classFeedback ?? '' }}"></div>
    </div>
@endif
