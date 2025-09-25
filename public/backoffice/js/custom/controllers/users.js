import App from "./../app.js";

const search_city = (city) => {
    App.ajax({path: `/ajax/search-city`, method: 'POST', data: { city }}).then((response) => {
        if (response.results.length > 0) {
            let html = '<ul>';
            response.results.forEach(item => {
                html += `<li class="select-city" data-city="${item.city}" data-div="ajax_city">${item.city} (${item.province_code}) - ${item.region}</li>`;
            })
            html += '</ul>';
            $('.ajax_city').html(html).show();
        } else {
            $('.ajax_city').hide();
        }
    })
}

const select_city = (item) => {
    const city = item.data('city');
    const div_name = item.data('div');
    const div = $(`.${div_name}`);
    div.html('').hide().parent().find(`input[name='city']`).val(city);
}

const create = () => {
    const form = '.create-user';
    const data = App.serialize(form);
    App.clearForm($(form))
    App.ajax({path: `/users/create`, method: 'POST', data: { ...data.data }}).then((response) => {
        App.sweet(App.translate('javascript.frontend.message-ok'), App.translate('javascript.frontend.perfect'), 'success', () => {
            location.href = response.url
        });
    }).catch(errors => {
        App.renderErrors(errors, $(form));
    })
}

const edit = () => {
    const form = '.edit-user';
    const id =  $(form).find(`input[name='id']`).val();
    const data = App.serialize(form);
    App.clearForm($(form))
    App.ajax({path: `/users/${id}`, method: 'PUT', data: { ...data.data }}).then((response) => {
        App.sweet(App.translate('javascript.frontend.message-ok'), App.translate('javascript.frontend.perfect'), 'success', () => {
            location.href = response.url
        });
    }).catch(errors => {
        App.renderErrors(errors, $(form));
    })
}


const init = () => {

    $(document).on('keyup', `.create-user input[name='city']`, App.debounce(function () {
        if ($(this).val().length > 2) {
            search_city($(this).val());
        }
    }, 500))

    $(document).on('click', '.select-city', function () {
        select_city($(this));
    })

    $(document).on('click', '.btn-create-user', function () {
        create($(this));
    })

    $(document).on('click', '.btn-edit-user', function () {
        edit($(this));
    })

}

const Users = {
    init,
}

export default Users
