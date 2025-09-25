@if(!isset($field))
    <div class="col-12 col-sm-<?=$col ?? '4'?> m-t-sm @if(isset($has_ajax)) position-relative @endif">
        <label>
            {{ $label ?? '' }}
        </label>
        @if($errors->has($name))
            <small class="text-danger">{{ $errors->first($name) }}</small>
        @endif
@endif
        @if(isset($searching))
            <div class="input-group">
            <span class="input-group-btn">
                <button type="button" class="btn btn-default searching-field">{{ __('backend/utils.actions.find') }}</button>
            </span>
        @endif
        <input
            type="{{ $type ?? 'text' }}"
            class="form-control {{ $class ?? '' }}"
            name="{{ $name }}"
            id="{{ $id_input ?? $name }}"
            @if(isset($id))
                data-id="{{ $id }}"
            @endif
            @if(isset($form))
                form="{{ $form }}"
            @endif
            @if(isset($type) && $type === 'password')
                value=""
            @else
                @if(isset($value))
                    value="{{ $value ?? '' }}"
                @else
                    value="{{ $object[$name] ?? '' }}"
                @endif
            @endif
            @if(isset($disabled) && $disabled)
                disabled
            @endif
            @if(isset($required))
                required
            @endif
            @if(isset($readonly) && $readonly)
                readonly
            @endif
            @if(isset($min))
                minlength="{{ $min }}"
                min="{{ $min }}"
            @endif
            @if(isset($max))
                maxlength="{{ $max }}"
            @endif
            @if(isset($autocomplete) or isset($searching))
                autocomplete="off"
                role="presentation"
            @endif
            @if(isset($step))
                step="{{ $step }}"
            @endif
            @if(isset($searching))
                data-model="{{ $searching['model'] }}"
                data-field="{{ $searching['field'] }}"
            @endif
            @if(isset($key))
                data-key="{{ $key }}"
            @endif
            @if(isset($placeholder))
                placeholder="{{ $placeholder }}"
            @endif
            @if(isset($onChange))
                onChange="{{ $onChange }}"
            @endif
            @if(isset($dataset))
                @foreach($dataset as $k => $v)
                    data-{{ $k }}="{{ $v }}"
            @endforeach
            @endif
        >
        @if(isset($has_ajax))
                <span class="ajax_{{ $name }} input_ajax_response"></span>
        @endif
        @if(isset($hidden_input))
                <input type="hidden" class="form-control {{ $hidden_input }} is-invalid" name="{{ $hidden_input }}" id="{{ $hidden_input }}" value="{{ $value_hidden_input ?? '' }}">
        @endif
        @if(isset($small))
                <small>{{ $small }}</small>
        @endif
        @if(isset($searching))
            </div>
        @endif
@if(!isset($field))
        <div class="invalid-feedback {{ $classFeedback ?? '' }}"></div>
    </div>
@endif

