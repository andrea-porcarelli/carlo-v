import App from "./../app.js";

const import_invoices = (response, message) => {
    const div = $(`.invoices-imported`);
    App.ajax({path: `/backoffice/invoices/import`, method: 'POST', data: { file: message[0] }}).then(response => {
        div.append(response.html)
    }).catch(error => {
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
}

const Suppliers = {
    init,
}

export default Suppliers
