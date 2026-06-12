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

String.prototype.format = String.prototype.StringFormat = function ()
{
    var s = this,
        i = arguments.length;

    while (i--)
    {
        s = s.replace(new RegExp('\\{' + i + '\\}', 'gm'), arguments[i]);
    }
    return s;
};

window.location.query = window.location.QueryString = function (name)
{
    name = name.replace(/[\[]/, "\\\[").replace(/[\]]/, "\\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);

    return results == null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}

Materialize.toast.reusable = function (message, displayLength)
{
    if (displayLength == undefined)
        displayLength = 3000;

    $("#toast-container .reusable").remove();
    Materialize.toast(message, displayLength, 'reusable');
}

function closeToast(classname)
{
    if (classname == undefined)
        classname = 'reusable';

    // (toast_effect, { direction: toast_firstDirection }, toast_duration)
    $("#toast-container ." + classname).hide('fade', 375, function ()
    {
        $("#toast-container ." + classname).remove();
    });
}
