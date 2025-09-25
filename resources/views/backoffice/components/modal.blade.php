<div class="modal" id="{{ $id }}"  aria-hidden="true" @isset($z_index) style="z-index: {{ $z_index }} !important;" @endisset>
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
