<div class="modal fade" id="removeStockModal" tabindex="-1" role="dialog" aria-labelledby="removeStockModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="removeStockModalLabel">Rimuovi Quantità</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="removeStockForm">
                <div class="modal-body">
                    <input type="hidden" id="remove_material_id" name="material_id">
                    <div class="form-group">
                        <label>Materiale</label>
                        <p class="form-control-static" id="remove_material_label" style="font-weight: bold;"></p>
                    </div>
                    <div class="form-group">
                        <label for="remove_quantity">Quantità da rimuovere <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="remove_quantity" name="stock" step="0.01" min="0.01" required>
                            <span class="input-group-addon" id="remove_unit"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="remove_notes">Note</label>
                        <textarea class="form-control" id="remove_notes" name="notes" rows="3" maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-danger" id="btnSaveRemoveStock">
                        <i class="fa fa-minus-circle"></i> Rimuovi Quantità
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>