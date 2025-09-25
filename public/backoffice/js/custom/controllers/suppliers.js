import App from "./../app.js";

const create_supplier = (button) => {
    App.update_or_create(button , 'POST', '.create-supplier', '/backoffice/suppliers/create', '/backoffice/suppliers');
}

const edit_supplier = (button) => {
    App.update_or_create(button, 'PUT', '.edit-supplier', `/backoffice/suppliers/${button.data('id')}`, '/backoffice/suppliers');
}


const init = () => {

    $(document).on('click', '.btn-create-supplier', function () {
        create_supplier($(this));
    })

    $(document).on('click', '.btn-edit-supplier', function () {
        edit_supplier($(this));
    })
}

const Suppliers = {
    init,
}

export default Suppliers
