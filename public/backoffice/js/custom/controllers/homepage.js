import App from "./../app.js";

const loadHomepageContent = (e) => {
    const language = e.val();
    App.ajax({ path: `/backoffice/homepage/get-contents`, method: 'post', data: { language}}).then(response => {
        $('.show-contents').html(response.html);
        App.initDropzone('upload-homepage-element', (file, message, element) => {
            $(element.element.dropzone.element).parent().parent().parent().parent().parent().find('form').first().append(`<input type='hidden' name='image' value='${message.destination}/${message.name}' />`);
        }, '.jpg,.jpeg,.png')
        App.loadSwitch('.show-contents')
    })
}

const editHomepageElement = (e) => {
    const element_id = e.data('id');
    const data = App.serialize(`.element-homepage-${element_id}`);
    App.ajax({ path: `/backoffice/homepage/content/${element_id}`, method: 'put', data: { ...data.data }}).then(response => {
        App.success(`.element-homepage-${element_id}`);
    })
}

const createHomepageElement = (e) => {
    const data = App.serialize(`.create-homepage-element`);
    const language = $('.language').val();
    App.ajax({ path: `/backoffice/homepage/store`, method: 'post', data: { ...data.data, language }}).then(response => {
        App.success(`.create-homepage-element`);
        setTimeout(() => {
            $("#dynamic-modal").modal('hide');
            loadHomepageContent($('.language'))
        }, 1500)
    })
}

const deleteHomepageElement = (e) => {
    const element_id = e.data('id');
    App.sweetConfirm('Sei sicuro di voler eliminare questo elemento?', () => {
        App.ajax({ path: `/backoffice/homepage/${element_id}`, method: 'delete'}).then(() => {
            App.sweet('Elemento cancellato', 'Ottimo!', 'success', () => {
                loadHomepageContent($('.language'))
            })
        })
    })

}

const sortableHomepage = (type, order) => {
    App.ajax({ path: `/backoffice/homepage/sort`, method: 'post', data: { type, order }}).then(() => {})
}

const open_cart = () => {
    App.ajax({ path: `/carts/open`, method: 'get'}).then(response => {
        $('#create-order').find('.modal-body').html(response.html);
        $('#create-order').modal('show');
        cart_init();
    }).catch(errors => console.log(errors))
}

const load_cart = () => {
    App.ajax({ path: `/carts/cart`, method: 'get'}).then(response => {
        $('.cart-products').html(response.html);
        $(document).trigger('loadSwitchTrigger', [{container: '.cart-products'}]);
    }).catch(errors => console.log(errors))
}
const load_products = () => {
    const only_service = $(`#create-order input[name='only_service']`).is(':checked');
    const search = $(`#create-order input[name='search']`).val();
    App.ajax({ path: `/products/search`, method: 'get', data: { only_service, search, source: 'cart' }}).then(response => {
        $('.list-products').html(response.html);
    }).catch(errors => console.log(errors))
}

const add_to_cart = (e) => {
    const product_id = e.data('product-id');
    App.ajax({ path: `/carts/add`, method: 'post', data: { product_id }}).then(response => {
        load_cart();
    }).catch(errors => console.log(errors))
}

const change_invoice_period = (e) => {
    const cart_product_id = e.data('cart-product-id');
    const invoice_period = e.val();
    App.ajax({ path: `/carts/change-invoice-period`, method: 'put', data: { cart_product_id, invoice_period }}).then(() => {
        load_cart();
    }).catch(errors => console.log(errors))
}

const change_tax_free = (e) => {
    const cart_product_id = e.data('cart-product-id');
    const tax_free = e.is(':checked');
    App.ajax({ path: `/carts/change-tax-free`, method: 'put', data: { cart_product_id, tax_free }}).then(() => {
        load_cart();
    }).catch(errors => console.log(errors))
}

const change_product_price = (e) => {
    const cart_product_id = e.data('cart-product-id');
    const price = e.val();
    App.ajax({ path: `/carts/change-product-price`, method: 'put', data: { cart_product_id, price }}).then(() => {
        load_cart();
    }).catch(errors => console.log(errors))
}

const change_quantity_product_cart = (e) => {
    const cart_product_id = e.parent().parent().find(`input[name='quantity']`).data('cart-product-id');
    const quantity = e.parent().parent().find(`input[name='quantity']`).val();
    App.ajax({ path: `/carts/change-quantity`, method: 'put', data: { cart_product_id, quantity }}).then(() => {
        load_cart();
    }).catch(errors => console.log(errors))
}

const store_order = (button) => {
    const user_id = $(`#create-order select[name='user_id']`).val();
    if (user_id === '') {
        App.sweet('Devi selezionare il cliente')
    } else {
        button.attr('disabled', 'disabled')
        App.sweetConfirm('Sei sicuro di voler registrare questo ordine?', () => {
            App.ajax({path: `/carts/store`, method: 'post', data: { user_id} }).then(response => {
                App.sweet("L'ordine è stato creato con successo", 'Perfetto', 'success');
                setTimeout(() => {
                    window.location.href = response.redirect
                }, 2500)
            }).catch(errors => console.log(errors))
        })
    }
}

const cart_init = () => {
    $(document).trigger('selectChoice', [{
        id: 'user_id',
        path: 'search-entities',
        body:{
            interface: 'UserInterface',
            field: ['email', 'firstname', 'lastname', 'phone'],
        }
    }]);

    load_products();
    load_cart();

    $(document).on('change', `#create-order input[name='only_service']`, function () {
        load_products();
    })

    $(document).on('keyup', `#create-order input[name='search']`, function () {
        load_products();
    })

    $(document).on('click', `#create-order .add-to-cart`, function () {
        add_to_cart($(this));
    })

    $(document).on('change', `#create-order .invoice-period`, function () {
        change_invoice_period($(this));
    })

    $(document).on('change', `#create-order .tax_free`, function () {
        change_tax_free($(this));
    })

    $(document).on("blur keyup", `#create-order .cart-product-price`,
        App.debounce(function () {
            change_product_price($(this));
        }, 500)
    );

    $(document).on('click', `#create-order .plus-minus-button`, function () {
        change_quantity_product_cart($(this))
    })

    $(document).on('click', `#create-order .btn-store-order`, function () {
        store_order($(this))
    })
}

const open_withdrawal = () => {
    App.ajax({ path: `/withdrawals/create`, method: 'get'}).then(response => {
        $('#store-withdrawal').find('.modal-body').html(response.html);
        $('#store-withdrawal').modal('show');
        withdrawals();
    }).catch(errors => console.log(errors))
}

const store_withdrawal = () => {
    const data = App.serialize('.store-withdrawal')
    App.ajax({ path: `/withdrawals`, method: 'POST', data: { ...data.data }}).then(() => {
        withdrawals();
    }).catch(errors => {
        App.sweet(errors.responseJSON.message)
    } )
}

const withdrawals = () => {
    App.ajax({ path: `/withdrawals/list`, method: 'GET'}).then(response => {
        $(`.withdrawals`).html(response.html);
    })
    App.ajax({ path: `/withdrawals/report`, method: 'GET'}).then(response => {
        $(`.withdrawals-report`).html(response.report);
    })
}

const init = () => {

    $(document).on('change', '.language', function () {
        loadHomepageContent($(this))
    })

    $(document).on('click', '.btn-edit-element-homepage', function () {
        editHomepageElement($(this))
    })

    $(document).on('click', '.btn-add-homepage-element', function () {
        App.openAddDynamicModal($(this), () => {
            $('.add-homepage-element-modal').find('.upload-homepage-element').find(`input[name='path']`).val($(this).data('type'));
            $('.add-homepage-element-modal').find('.create-homepage-element').find(`input[name='type']`).val($(this).data('type'));
            App.initDropzone('upload-homepage-element', (file, message, element) => {
                $(element.element.dropzone.element).parent().parent().parent().parent().parent().find('form').first().append(`<input type='hidden' name='image' value='${message.destination}/${message.name}' />`);
            }, '.jpg,.jpeg,.png')
        });
    })

    $(document).on('click', '.btn-create-homepage-element', function () {
        createHomepageElement($(this))
    })

    $(document).on('click', '.btn-delete-element-homepage', function () {
        deleteHomepageElement($(this))
    })

    $(document).on('click', '.open-type', function () {
        const type = $(this).data('type');
        $(`.sortable_${type}`).toggle(250);
        const arrow = $(this).find('.open-type-arrow')
        if (arrow.hasClass('fa-arrow-circle-up')) {
            arrow.removeClass('fa-arrow-circle-up').addClass('fa-arrow-circle-down');
        } else {
            arrow.removeClass('fa-arrow-circle-down').addClass('fa-arrow-circle-up');
        }
    })

    $(document).on("sortable_homepage", function (e, parameters) {
        sortableHomepage(parameters.type, parameters.order);
    });

    $(document).on('click', '.btn-create-new-order', function (e) {
       open_cart();
    });

    $(document).on('click', '.btn-open-withdrawal', function (e) {
       open_withdrawal();
    });

    $(document).on('click', '.btn-store-withdrawal', function (e) {
       store_withdrawal();
    });


}

const Homepage = {
    init,
}

export default Homepage
