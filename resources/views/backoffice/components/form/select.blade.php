@if(!isset($field))
    <div class="col-xs-12 col-sm-<?=$col ?? '4'?> m-t-sm">
        <label>
            {{ $label ?? '' }}
        </label>
        @if($name === 'meta[invoice_type]' && $errors->has('meta.invoice_type'))
            <small class="text-danger">{{ $errors->first('meta.invoice_type') }}</small>
        @endif
@endif
    <select
        @if(isset($onchange))
            onchange="{{ $onchange }}"
        @endif
        name="{{ $name }}"
        class="form-control {{ $class ?? '' }}"
        id="{{ $name }}"
        @if(isset($id))
            data-id="{{ $id }}"
        @endif
        @if(isset($multiple))
            multiple
        @endif

        @if(isset($form))
            form="{{ $form }}"
        @endif
        @if(isset($disabled) && $disabled)
            disabled
        @endif
        @if(isset($readonly) && $readonly)
            readonly
        @endif
        @if(isset($dataset))
            @foreach($dataset as $k => $v)
                data-{{ $k }}="{{ $v }}"
            @endforeach
        @endif
    >
        <option value="{{ $first_value ?? '' }}" @if (!isset($hide_first)) disabled selected value @endif>{{ $first_value_text ?? 'Seleziona' }}</option>
        @isset($options)
            @foreach($options as $option)
                <option
                        value="{{ $option['id'] }}"
                        @if(isset($multiple))
                            @if((isset($value) && in_array($option['id'], explode(',', $value))) || (isset($values) && in_array($option['id'], $values)))
                            selected
                            @endif
                        @else
                            @if (isset($object) && !isset($value))
                                @if($object->{$name} == $option['id'])
                                    selected
                               @endif
                            @else
                                @if((isset($value) && $value == $option['id']) || (isset($values) && in_array($option['id'], $values)))
                                    selected
                                @endif
                            @endif
                        @endif
                >{{ $option['label'] }}</option>
            @endforeach
        @endisset
        @isset($options_raw)
            @foreach($options_raw as $option)
                <option
                        value="{{ $option }}"
                        @if((isset($value) && $value == $option) || (isset($values) && in_array($option, $values))))
                        selected
                        @endif
                >{{ $option }}</option>
            @endforeach
        @endisset
        @isset($groups)
            @foreach($groups as $k => $group)
                <optgroup label="{{ $k }} {{ $group['label'] }}">
                    @foreach($group['elements'] as $option)
                        <option
                            value="{{ $option['id'] }}"
                            @if((isset($value) && $value == $option['id']) || (isset($values) && in_array($option['id'], $values)))
                            selected
                            @endif
                        >{{ $option['label'] }}</option>
                    @endforeach
                </optgroup>
            @endforeach

        @endisset
    </select>
@if(!isset($field))
        <div class="invalid-feedback {{ $classFeedback ?? '' }}"></div>
    </div>
@endif
