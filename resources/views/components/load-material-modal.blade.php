<div class="modal fade" id="addStockModal" tabindex="-1" role="dialog" aria-labelledby="addStockModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="addStockModalLabel">Aggiungi Giacenza</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addStockForm">
                <div class="modal-body">
                    <input type="hidden" id="stock_material_id" name="material_id">
                    <div class="form-group">
                        <label>Materiale</label>
                        <p class="form-control-static" id="stock_material_label" style="font-weight: bold;"></p>
                    </div>
                    <div class="form-group">
                        <label for="stock_quantity">Quantità <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="stock_quantity" name="stock" step="0.01" min="0.01" required>
                            <span class="input-group-addon" id="stock_unit"></span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="stock_purchase_date">Data di Acquisto</label>
                        <input type="date" class="form-control" id="stock_purchase_date" name="purchase_date">
                    </div>
                    <div class="form-group">
                        <label for="stock_purchase_price">Prezzo di Acquisto</label>
                        <div class="input-group">
                            <span class="input-group-addon">&euro;</span>
                            <input type="number" class="form-control" id="stock_purchase_price" name="purchase_price" step="0.01" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="stock_notes">Note</label>
                        <textarea class="form-control" id="stock_notes" name="notes" rows="3" maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveStock">
                        <i class="fa fa-save"></i> Salva Giacenza
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
