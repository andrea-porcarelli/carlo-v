@props([
    'class' => null,
    'size' => 'md',
    'title' => null
])
<div class="modal {{ $class }}" id="dynamic-modal"  aria-hidden="true">
    <div class="modal-dialog modal-{{ $size ?? 'lg' }}">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ $title ?? '' }}</h4>
            </div>
            <div class="modal-body">

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-white" data-dismiss="modal">Chiudi</button>
            </div>
        </div>
    </div>
</div>
