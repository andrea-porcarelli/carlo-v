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

// read and restore menu
// when user has been authorized
function loadMenu()
{
    if (window.localStorage !== undefined) {
        // read the token value
        // when the browser support the "local storage" and
        // exists a valid token 

        var token = window.localStorage.getItem("token");
        if (token !== null)
        {
            // get menu
            menu = getMenu();

            // set menu
            $('.app').addClass('lf240');            
            $("#menu").html(menu);
            
            // set brand name into menu
            var brandname = window.localStorage.getItem("brandname");
            $("#brandname-h").html(brandname);
            $("#brandname-v").html(brandname);

            // localize menu
            localize("menu");

            $('#spinner').addClass('lf240');
            $(".button-collapse").sideNav();
        }
    }
}

function unloadMenu()
{
    $("#menu").html('');
}

// EVENT CLICK ON MENU' ITEM
$(document).on('click', 'ul#slide-out a', function (e)
{
    var page = $(this).attr('href').match(/#(.*$)/)[1];

    $(function ()
    {
        $('.button-collapse').sideNav('hide');
        setTimeout(function () { pageLoader(page); }, 250);
    });

});

$(document).on('click', '.button-collapse', function (e)
{
    $(".button-collapse").sideNav();
});




$(document).on("click", "#logout", function () {
    //push deregistration
    //unRegister(window.localStorage.getItem("token"));
    window.localStorage.removeItem("token");
    window.localStorage.removeItem("userName");
    unloadMenu();
    //window.location.href = "index.html#login";
    //setTimeout(function () { setPage('login'); }, 50);
    setPage('login');
});


// READ AND RESTORE MENU
function getMenu()
{
    var res = "";

    // attempting to load the menu
    $.ajax({
        async: false,
        type: "GET",
        url: "js/app/include.menu.xml",
        dataType: "text",
        success: function (result) { res = result; },
        error: function (result) { res = "<nav><ul class='right hide-on-med-and-down'></ul></nav>" }
    });

    //// attach an event on all buttons
    //res += '   <script>                                                ' +
    //       '      $(".button-collapse").sideNav();                     ' +
    //       '   </script>                                               ';

    //// set event on logout button
    //res += '   <script>                                                ' +
    //       '       $(document).on("click", "#logout", function ()      ' +
    //       '       {                                                   ' +
    //       '           window.localStorage.removeItem("token");        ' +
    //       '           window.localStorage.removeItem("userName");     ' +
    //       '           unloadMenu();                                   ' +
    //       '           window.location.href = "index.html#login";      ' +
    //       '       });                                                 ' +
    //       '   </script>                                               ';

    //// translate menu items
    //res += '   <script>                                                             ' +
    //       '       $(document).ready(function()                                     ' +
    //       '       {                                                                ' +
    //       '            var brandname = window.localStorage.getItem("brandname");   ' +
    //       '            $("#brandname-h").html(brandname);                          ' +
    //       '            $("#brandname-v").html(brandname);                          ' +
    //       '            localize("menu");                                           ' +
    //       '       });                                                              ' +
    //       '   </script>                                                            ';

    return res;
}


