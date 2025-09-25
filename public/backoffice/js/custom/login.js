import App from "./app.js";

const login = () => {
    const serialized = App.serialize('.form-login');
    App.ajax({path: `/backoffice/login`, method: 'post', data: serialized.data}).then(response => {
        App.sweet('Login effettuato', 'Ok!', 'success')
        setTimeout(() => {
            location.href = response.url
        }, 1000)
    }).catch(errors => {
        App.sweet(errors.responseJSON.message ?? '', 'Errore')
        App.renderErrors(errors, $('.form-login'))
    })
}
const logout = () => {
    App.ajax({path: `/backoffice/logout`, method: 'post'}).then(() => {
        location.href = '/backoffice/login'
    })
}
const resetPassword = () => {
    const serialized = App.serialize('.reset-password');
    App.ajax({path: `/backoffice/reset-password`, method: 'post', data: serialized.data}).then(response => {
        App.sweet('La password è stata aggiornata con successo', 'Perfetto!', 'success', () => {
            location.href = response.url;
        })

    }).catch(errors => {
        App.sweet(errors.responseJSON.message)
    })
}

const init = () => {

    $(document).on('click', '.btn-login', function () {
        login()
    })

    $(document).on('click', '.btn-logout', function () {
        logout()
    })

    $(document).on('click', '.btn-reset-password', function () {
        resetPassword()
    })

    $(".form-login input").each(function () {
        $(this).keypress((e) => {
            if (e.keyCode === 13) {
                login();
            }
        });
    });
}

const Login = {
    init,
}

export default Login
