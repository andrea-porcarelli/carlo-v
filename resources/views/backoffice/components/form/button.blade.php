@if(!isset($field))
<div class="col-xs-12 col-sm-<?=$col ?? '4'?> m-t-sm">
    @endif
    @if(isset($with_add))
        <div class="btn-group">
    @endif
    <button
        type="{{ $type ?? 'button' }}"
        class="btn btn-primary {{ $class ?? '' }}"
        data-model="{{ $model ?? '' }}"
        @if(isset($id))
            data-id="{{ $id }}"
        @endif
        @if(isset($readonly))
            readonly
        @endif
        @if(isset($disabled) && $disabled)
            disabled
        @endif
        @if(isset($trigger))
         data-trigger="{{ $trigger }}"
        @endif
        @if(isset($form_name))
         data-form-name="{{ $form_name }}"
        @endif
        @if(isset($redirect))
         data-redirect="{{ $redirect }}"
        @endif
        @if(isset($confirm))
         data-confirm="{{ $confirm }}"
        @endif
        @if(isset($model))
         data-model="{{ $model }}"
        @endif
        @if(isset($dataset))
            @foreach($dataset as $k => $v)
                data-{{ $k }}="{{ $v }}"
        @endforeach
        @endif
    >{!! $label !!}</button>
    @if(isset($with_add))
        @if(!empty($route))
            <a href="{{ route($route) }}" class="btn btn-info btn-add {{ $class_btn_add ?? '' }}"> <span class="fa fa-plus-circle"></span> Crea</a>
        @else
            <button class="btn btn-info btn-add {{ $class_btn_add ?? '' }}" data-model="{{ $model }}" >
                <span class="fa fa-plus-circle"></span> Crea
            </button>
        @endif
        </div>
    @endif
@if(!isset($field))
</div>
@endif
