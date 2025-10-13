import App from "./../app.js";

const import_invoices = (response, message) => {
    const div = $(`.invoices-imported`);
    App.ajax({path: `/backoffice/invoices/import`, method: 'POST', data: { file: message[0] }}).then(response => {
        div.append(response.html)
    }).catch(error => {
        App.sweet(error.responseJSON.message, 'Errore', 'warning')
    })
}

const map_products = (btn) => {
    const invoice_id = btn.data('invoice-id');
    const form = App.serialize(`#mappingForm`);
    console.log(form);
    App.ajax({path: `/backoffice/invoices/${invoice_id}/store-mapping-products`, method: 'POST', data: form.data}).then(response => {
        App.sweet('La mappatura è stata salvata corretamente', 'Ottimo', 'success', () => {
            window.location.href =  `/backoffice/invoices`;
        })
    }).catch(error => {
        console.log(error)
        App.sweet(error.responseJSON.message, 'Errore', 'warning')
    })
}

const init = () => {

    $(document).on('click', '.btn-load-invoice', function () {
        App.openAddDynamicModal($(this), () => {
            $(document).trigger('upload', [{
                class: 'upload-invoice',
                acceptedFiles: '.xml',
                multiple: true,
                maxFiles: 5,
                callback: (files, message) => {
                    import_invoices(files, message)
                }
            }]);
        })
    })
    $(document).on('click', '.btn-store-map-products', function () {
        map_products($(this))
    });


}

const Suppliers = {
    init,
}

export default Suppliers
