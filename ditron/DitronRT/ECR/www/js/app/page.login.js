/*
 * @title
 * @description
 * @name 
 * 
 * @author Copyright 2015 Ivan Barbato
 * @license 
 * @see 
 * @version 1.0.0.0
 */

// localize page (as <res></res> element)
//localize();

// start functions of the specific page
page_credentials();

// LOAD FUNCTION OF CREDENTIAL PAGE
function page_credentials() {
    // read a valid token if it exists into "storage"
    IdentityVerification();

    // set the validation functions on authentication form
    setValidation();
}

// READ A VALID TOKEN IF IT EXISTS INTO "STORAGE"
function IdentityVerification() {
    if (window.localStorage !== undefined) {
        // read the token value
        // when the browser support the "local storage" and
        // exists a valid token 

        var token = window.localStorage.getItem("token");
        if (token !== null) {

            //window.location.href = "index.html#home";
            setPage('home');
        }
    }
    else {
        // show a warning message 
        // when the browser not support the "local storage"

        Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + localizeSingleWord("common", "browser_outdated"));
    }
}

// SET THE VALIDATION FUNCTIONS ON AUTHENTICATION FORM
function setValidation() {
    if (window.localStorage !== undefined) {
        // get the "culture info" from local storage
        var culture = getCulture();

        if (culture != null) {
            culture = culture.match(/\w+/);

            if (culture != 'en') {
                // translating the validation messages
                var resource = "js/localization.validate/messages_" + culture + ".js";
                $.getScript(resource);
            }
        }
    }

    // set defaults of validator 
    $.validator.setDefaults({
        errorClass: 'invalid',
        validClass: "valid",
        errorPlacement: function (error, element) {
            $(element)
                .closest("form")
                .find("label[for='" + element.attr("id") + "']")
                .attr('data-error', error.text());
        },
        submitHandler: function (form) {
            // invoke the login on form submit 
            login($("#username").val(), $("#password").val());
        }
    });

    // attach the validator on authentication form 
    $("#form").validate({
        rules: {
            dateField: {
                date: true
            }
        }
    });
}

// EXECUTE LOGIN
function login(username, password) {
    // prepare json object data 
    var param = { 'Username': username, 'Password': password }

    // start preloader
    startPreloader();

    // send request
    $.ajax({
        async: true,
        type: "POST",
        url: WS_URL + "/cgi-bin/ap1.fcrwra/cmd/login",
        data: "username=" + username + "&password=" + password,
        contentType: "application/x-www-form-urlencoded; charset=utf-8"
    }).done(function (data) {
        // end preloader
        endPreloader();

        // show a warning when token is empty
        //Materialize.toast('<i class="mdi-cst-info"></i>' + data + "<br/>", 5000);

        /*
        if (typeof data !== "undefined" && data !== null && data.Instances !== null)
        {
            if ((data.Instances[0]).Auth == "")
            {
                // show a warning when token is empty
                Materialize.toast('<i class="mdi-cst-warning"></i>' + data.error.description, 3000);
            }
            else
            {
                */
        // save into local storage the received server authentication data

        window.localStorage.setItem("token", "token");
        window.localStorage.setItem("brandname", "&nbsp;");
        /*
        window.localStorage.setItem("token", make_base_auth(data.Instances[0].Auth, ""));
        window.localStorage.setItem("brandname", data.Instances[0].Description);

        */
        // redirect to home page
        //window.location.href = "index.html#home";
        setPage('home');

        //initPushNotification();
        setTimeout(function () {
            //pageLoader('home');
            loadMenu();
        }, 250);
        /*
    }
}
*/
    }).fail(function (data) {
        // end preloader
        endPreloader();

        // show a warning when server data is null
        Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + data.statusText + ' [' + data.status + ']');
    });
}

// MAKE BASE AUTHENTICATION VALUE
function make_base_auth(user, password) {
    var tok = user + ':' + password;
    var hash = btoa(tok);
    return "Basic " + hash;
}
