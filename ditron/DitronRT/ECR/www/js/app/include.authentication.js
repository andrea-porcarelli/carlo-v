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

// INVOKE THE 'CHECKAUTHORIZE' FUNCTION
//checkAuthorize();

// AUTHORIZE ACCESS TO PARENT PAGE
function checkAuthorize()
{
    if ($('body').hasClass('authorize'))
    {
        if (window.localStorage !== undefined)
        {
            window.localStorage.setItem("token", "token");
            window.localStorage.setItem("brandname", "DITRON fcrm v1.0");

            // read the token value
            // when the browser support the "local storage" and
            // exists a valid token 

            var token = window.localStorage.getItem("token");
            if (token == null) {
                //window.location.href = "index.html#login";
                setPage('login');
                return false;
            } else {
                //window.location.href = "index.html#home";
                setPage('home');
                return true;
            }
        }
        else
        {
            // show a warning message 
            // when the browser not support the "local storage"

            Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + localizeSingleWord("common", "browser_outdated"));
            //window.location.href = "index.html#login";
            setPage('login');
        }
    }
    return false;
}

