@if(!isset($field))
    <div class="col-xs-12 col-sm-<?=$col ?? '4'?> m-t-sm">
    <label>
        {!! $label ?? '' !!}
    </label><br />
@endif
        <input
                type="checkbox"
                class="js-switch {{ $class ?? ''}}"
                name="{{ $name }}"
                @if(isset($value) && $value)
                    checked
                @endif
        />
@if(!isset($field))
    <div class="invalid-feedback"></div>
</div>
@endif
