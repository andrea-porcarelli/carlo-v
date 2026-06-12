/*
 * @title
 * @description
 * @name 
 * 
 * @author Copyright 2017 Ivan Barbato
 * @license 
 * @see 
 * @version 1.0.0.0
 */

// INVOKE THE 'CHECKAUTHORIZE' FUNCTION
onDeviceReady();



function onDeviceReady()
{
    // start functions of the specific page
    if (checkAuthorize())
    {
        loadMenu();
    }

    // TODO: Cordova has been loaded. Perform any initialization that requires Cordova here.
};

$.ajaxSetup({
    cache: true
});

function setPage(page)
{
    window.location.href = "index.html#" + page;
    pageLoader(page);
}

// LOAD FUNCTION OF BOX PAGE
function pageLoader(page)
{
    // load an new page
    $("#success").load(page + ".html #inner-box", function (response, status, xhr)
    {
        if (status == "error")
        {
            var msg = "Sorry but there was an error: ";
            $("#error").html(msg + xhr.status + " " + xhr.statusText);
        }
        else
        {
            $('#title-on-menu res').text(localizeSingleWord(page, 'title'));

            // localize the called page
            localize(page);

            // load scripts' page 
            $.getScript("js/app/page." + page + ".js");
        }
    });
}
