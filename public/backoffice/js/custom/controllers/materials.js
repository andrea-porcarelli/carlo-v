import App from "./../app.js";

const show_modal = (e) => {
    const materialId = e.data('id');
    const materialLabel = e.data('label');
    const stockType = e.data('stock-type');

    const stockTypes = {
        'pz': 'Pezzi',
        'g': 'Grammi (g)',
        'ml': 'Millilitri (ml)'
    };

    $('#stock_material_id').val(materialId);
    $('#stock_material_label').text(materialLabel);
    $('#stock_unit').text(stockTypes[stockType] || stockType);

    // Reset form
    $('#addStockForm')[0].reset();
    $('#stock_material_id').val(materialId);
    $('#stock_purchase_date').val(new Date().toISOString().split('T')[0]);

    $('#addStockModal').modal('show');
}



const init = () => {

    $(document).on('click', '.btn-add-stock', function() {
        show_modal($(this))
    });

    $(document).on('submit', '#addStockForm', function(e) {
        e.preventDefault();

        const materialId = $('#stock_material_id').val();
        const formData = {
            stock: $('#stock_quantity').val(),
            purchase_date: $('#stock_purchase_date').val() || null,
            purchase_price: $('#stock_purchase_price').val() || null,
            notes: $('#stock_notes').val() || null
        };

        $('#btnSaveStock').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Salvataggio...');
        App.ajax({
            path: '/backoffice/restaurant/materials/' + materialId + '/stock',
            data: formData,
            method: 'POST',
        }).then(() => {
            $('#addStockModal').modal('hide');
            toastr.success('Giacenza aggiunta con successo');
            // Ricarica datatable
            $('.datatable_table').DataTable().ajax.reload();
        }).catch((xhr) => {
            let message = 'Errore durante il salvataggio';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }
            toastr.error(message);
        })
    });

}

const Materials = {
    init,
}

export default Materials
