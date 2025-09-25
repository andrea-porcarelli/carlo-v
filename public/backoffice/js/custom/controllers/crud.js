import App from "./../app.js";

const load_media = (parameters) => {
    const data = {
        entity_id: parameters.entity_id,
        entity_type: parameters.entity_type,
        media_type: parameters.media_type
    };
    App.ajax({ path: `/media`, method: 'POST', data }).then(response => {
        $(parameters.container).html(response.html)
    }).catch(errors => {
        console.log(errors)
    })
}

const attach_media = (parameters) => {
    const data = {
        entity_id: parameters.entity_id,
        entity_type: parameters.entity_type,
        media_type: parameters.media_type,
        item: parameters.item
    };
    App.ajax({ path: `/media`, method: 'PUT', data }).then(response => {
        $(parameters.container).html(response.html)
    }).catch(errors => {
        console.log(errors)
    })
}

const init = () => {

    $(document).on('click', '.btn-execute', function () {
        const route = $(this).data('route');
        const id = $(this).data('id');
        App.update_or_create(id === undefined ? 'POST' : 'PUT','.form-element', `/${route}/${id === undefined ? 'create' : id}`, `/${route}`, $(this));
    })

    $(document).on("load_media",  function (e, parameters) {
        load_media(parameters)
    });

    $(document).on("attach_media",  function (e, parameters) {
        attach_media(parameters)
    });

}

const Crud = {
    init,
}

export default Crud
