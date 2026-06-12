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

// read and restore preloader
preloader = getPreloader();
document.write(preloader);

// READ AND RESTORE PRELOADER
function getPreloader()
{
    var res = "";

    // attempting to load the dictionary that match with user language
    $.ajax({
        async: false,
        type: "GET",
        url: "js/app/include.preloader.xml",
        dataType: "text",
        success: function (result) { res = result; },
        error: function (result) { res = "<div></div>" }
    });

    return res;
}

// EXecute WIth Preloader 
// SHOW A PRELOADER SYMBOL WHILE EXECUTION OF INPUT FUNCTION
function exWiP(foo)
{
    // show preloader
    $("#spinner").show("slow", function ()
    {
        // execute input function
        foo;

        // hide preloader
        $("#spinner").hide();
    });
}

// START PRELOADER
function startPreloader()
{
    $("#spinner").show();
    $("#preloader").show();
}

// END START PRELOADER
function endPreloader()
{
    //setTimeout(function () { $("#spinner").hide(); }, 10000);
    $("#spinner").hide();
}

