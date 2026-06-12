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
initPicker();

function initPicker() {
    var culture = getCulture();

    // localize datepicker
    if (culture != 'en-GB') {
        $.fn.pickatime = { defaults: {} };
        culture = culture.replace('-', '_');
        $.getScript('js/localization.picker/' + culture + '.js');
    }
}

function setCurrentDate(date)
{
    // save "current-date"
    if (window.sessionStorage !== undefined)
        window.sessionStorage.setItem("current-date", parseInt(date));
}

function getCurrentDate()
{
    var d = new Date().getTime();

    // get stored "current-date"
    if (window.sessionStorage !== undefined)
    {
        d = window.sessionStorage.getItem("current-date");
        if (d == null) d = new Date().getTime();
    }
    return parseInt(d);
}

// EVENT CLICK ON CAPTURE BUTTON
$(document).on("click", '#datepicker-icon',
    function ()
    {
        $('.datepicker').pickadate('picker').open();
    });
