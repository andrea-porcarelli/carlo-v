@if(!isset($field))
    <div class="col-xs-12 col-sm-<?=$col ?? '4'?> m-t-sm">
        <label>
            {{ $label ?? '' }}
        </label>
    @endif
    <div class="input-group m-t-xs">
        <span class="input-group-btn">
            <button type="button" class="quantity-left-minus btn btn-xs btn-default"  data-type="minus" data-field="">
              <span class="glyphicon glyphicon-minus"></span>
            </button>
        </span>
        <input
            type="number"
            id="{{ $name }}" name="{{ $name }}"
            class="form-control input-number text-center"
            value="{{ $value ?? 0 }}"
            data-min="{{ $min ?? 1 }}"
            data-max="{{ $max ?? 100 }}"
            @if(isset($dataset))
                @foreach($dataset as $k => $v)
                    data-{{ $k }}="{{ $v }}"
                @endforeach
            @endif

            @if(isset($form))
                form="{{ $form }}"
            @endif
        >
        <span class="input-group-btn">
            <button type="button" class="quantity-right-plus btn btn-xs btn-default" data-type="plus" data-field="">
                <span class="glyphicon glyphicon-plus"></span>
            </button>
        </span>
    </div>
@if(!isset($field))
    <div class="invalid-feedback {{ $classFeedback ?? '' }}"></div>
</div>
@endif
