<div class="modal-header" role="dialog" aria-labelledby="modalTitle">
    <h5 class="modal-title" id="modalTitle">
        {{ $title }}
    </h5>
    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('Fermer') }}">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<div class="modal-body">
{{ $slot }}
</div>
