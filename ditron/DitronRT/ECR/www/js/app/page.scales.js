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
var firstAccess = true;
var retry_timeout;

// set pickdate
$('.datepicker').pickadate({
    selectMonths: true, // Creates a dropdown to control month
    selectYears: 15, // Creates a dropdown of 15 years to control year
    container: 'body'
});

var datepicker = $('.datepicker').pickadate('picker');

// localize page (as <res></res> element)
localize('scales');

// start functions of the specific page
page_scales();



// LOAD FUNCTION OF SCALES PAGE
function page_scales()
{
    // end preloader
    endPreloader();

    $("#toast-container .reusable").remove();
    $('.modal').modal();
    //if (window.sessionStorage.getItem("getScalesDataLock") == 'true')
    //    window.sessionStorage.setItem("getScalesDataLock", 'false');

    // EVENTS   
    datepicker.on('set', function (data)
    {
        if (data.select == null || data.select == "")
        {
            //do nothing
        } else
        {
            var d = new Date(datepicker.get('select', "yyyy-mm-dd")).getTime();
            if (d != getCurrentDate())
            {
                //do what you want to do            
                setCurrentDate(d);
            }
        }
    });
    datepicker.on('close', function (data) { });

    $('.timepicker').pickatime({
        default: 'now',
        twelvehour: false, // change to 12 hour AM/PM clock from 24 hour
        donetext: 'DONE',
        autoclose: false,
        vibrate: true, // vibrate the device when dragging clock hand
        container: 'body'
    });

    $(document).on("click", '#applyFilters', function () { getScalesData(); });
    $(document).on("click", '#restart', function () { restart(); });
    $(document).on("click", '#datetimeRenewal', function () { $('#modal-for-settime').modal('open'); });
    $(document).on("click", '#setDateTime', function () { setDateTime(); });

    // PULL AREA MANAGEMENT
    $('#pullarea').xpull({ 'callback': function () { getScalesData(); } });

    // set the table sort
    $("#grid").tablesorter({ debug: false });

    // get scales data
    getScalesData();
}



// GET SCALES DATA
function getScalesData()
{
    if (window.sessionStorage.getItem("getScalesDataLock") !== 'true')
    {
        window.sessionStorage.setItem("getScalesDataLock", 'true');

        if (firstAccess)
            // start preloader
            startPreloader();

        // send request
        {
            var baseUrl = window.sessionStorage.getItem("uriFM") + "/GROUPS/" + window.sessionStorage.getItem("groupId");

            var settings = {
                async: true,
                cache: false,
                contentType: "application/json; charset=utf-8",
                crossDomain: true,
                dataType: "json",
                type: "GET",
                url: baseUrl + "/STATUS",
                timeout: 10000
            }

            $.ajax(settings).always(function (data, textStatus, jqXHR)
            {
                window.sessionStorage.setItem("getScalesDataLock", 'false');

                if (firstAccess)
                {
                    // end preloader
                    endPreloader();
                }

                if (jqXHR.status == 200)
                {
                    if (firstAccess)
                        firstAccess = false;

                    if (typeof data !== "undefined" && data !== null)
                    {
                        // read all scales data
                        $.each(data.ScalesInfo, function (index, value)
                        {
                            if (typeof value !== "undefined" && value !== null)
                            {
                                // updating scales data when them exists
                                if (update(value) == 0)
                                {
                                    // adding scales data when them not exists
                                    insert(value);
                                }
                            }
                        });
                    }
                }
                else
                {
                    // show a warning when server data is null
                    // Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + jqXHR.statusText + ' [cod. ' + jqXHR.status + ']');
                }

                // get scales data periodically
                clearTimeout(retry_timeout);
                retry_timeout = setTimeout(function () { getScalesData(); }, 10000);
            });
        }


    }
    else
    {
        Materialize.toast.reusable('<i class="mdi-cst-waiting"></i>' + localizeSingleWord("common", "wait_response"));
    }
}

// UPDATING SCALES DATA
function update(value)
{
    var res = 0;

    $("#body tr[token='" + value.Id + "']").each(function (index, row)
    {
        // updating a row by specific reference

        // update status
        //this.cells[3].children[0].innerHtml = '<span class="chart-legend" status="' + value.Connected + '">&nbsp;</span>';
        $(this).find("td").eq(3).children(0).html('<span class="mdi-cst-status" status="' + value.Connected + '">&nbsp;</span>');

        // highlight the updated row
        highlight(row.getAttribute("id"), '#e1e1e1');

        // sort table data
        tableSort('#grid', 1, 0);

        res = 1
    });

    return res;
}

// ADDING SCALES DATA
function insert(value)
{
    // adding new row after the last row into tbody session
    var rowId = $("#grid tr").length;
    var $newRow = $('<tr id="row' + rowId + '" token="' + value.Id + '">' +
                        '<td>' + '<input type=\"checkbox\" name=\"select_group\" id=\"chk_' + value.Id + '\"  onclick=\"setGlobalSelector(\'chk_all\', \'select_group\')\" class=\"filled-in\" /><label for=\"chk_' + value.Id + '\">&nbsp;</label>' + '</td>' +
                        '<td>' + value.Id + '</td>' +
                        '<td>' + value.IpAddress + '</td>' +
                        '<td>' + '<span class="mdi-cst-status" status="' + value.Connected + '">&nbsp;</span>' + '</td>' +
                    '</tr>');

    // setting the correct column's alignment and size
    setSize($newRow, '#gridH');

    // adding hidden divs around the content of all the TD tags
    $newRow.hide();
    $newRow.find('td').wrapInner('<div style="display:none;" />');

    // adding this row after the first row
    $('#body').append($newRow);

    // show new row with delay effect
    //$('#row' + rowId).timer({
    //    delay: 2000 * rowId,
    //    repeat: false,
    //    callback: function ()
    //    {

    // show and blink the new row
    $('#row' + rowId).show();
    $('#row' + rowId + ' div').slideDown(700);
    highlight('row' + rowId, '#da0920');

    // sort table data
    tableSort('#grid', 1, 0);
    //    }
    //});

    return 1;
}



// RESTART QUESTION
function restart()
{
    var selectedItems = $('input[name="select_group"]:checked').length;

    if (parseInt(selectedItems) == 0)
    {
        msg = '<i class="mdi-cst-warning"></i>';
        msg += localizeSingleWord("scales", "no_reboot_conditions");

        Materialize.toast.reusable(msg);
    }
    else
    {
        msg = '<i class="mdi-cst-question"></i>';
        msg += localizeSingleWord("scales", "ask_reboot");
        msg += '<button id="confirmRestart" class="btn-floating btn waves-effect waves-light green" type="button" onclick="closeToast(); execRestart(); "><i class="material-icons center">done</i></button>';
        msg += '<button id="deleteRestart" class="btn-floating btn waves-effect waves-light red" type="button" onclick="closeToast(); resetSelection(\'chk_all\', \'select_group\');"><i class="material-icons center">clear</i></button>';

        Materialize.toast.reusable(msg, 10000);
    }
}

// RESTART EXECUTION
function execRestart()
{
    var reset = false;

    $('input[name="select_group"]:checked').each(function ()
    {
        reset = true;

        var id = this.id.replace('chk_', '');
        restartSingleScale(id);
    });

    if (reset)
    {
        resetSelection('chk_all', 'select_group');
    }
}

// RESTART SINGLE SCALE
function restartSingleScale(id)
{
    if (firstAccess)
        // start preloader
        startPreloader();

    // send request
    {
        var baseUrl = window.sessionStorage.getItem("uriFM") + "/GROUPS/" + window.sessionStorage.getItem("groupId");
        var params = "";
        {
            params += "?id=" + id;
        }

        var settings = {
            async: true,
            cache: false,
            contentType: "application/json; charset=utf-8",
            crossDomain: true,
            dataType: "json",
            type: "POST",
            url: baseUrl + "/RESTART" + params,
            timeout: 10000
        }

        $.ajax(settings).always(function (jqXHR)
        {
            if (firstAccess)
            {
                // end preloader
                endPreloader();
            }

            if (jqXHR.status == 200)
            {
                if (firstAccess)
                    firstAccess = false;

                $("#body tr[token='" + id + "']").each(function (index, row)
                {
                    // updating a row by specific reference

                    // update status
                    $(this).find("td").eq(3).children(0).html('<span class="mdi-cst-status" status="false">&nbsp;</span>');

                    // highlight the updated row
                    highlight(row.getAttribute("id"), '#e1e1e1');
                });

                // show a warning when server data is null
                Materialize.toast.reusable('<i class="mdi-cst-info"></i>' + localizeSingleWord("scales", "rebooting") + ' (id. ' + id + ')');
            }
            else
            {
                // show a warning when server data is null
                Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + jqXHR.statusText + ' [cod. ' + jqXHR.status + ']');
            }
        });
    }
}



// SET TIME 
function setDateTime()
{
    var atLeastOne = false;

    $('input[name="select_group"]').each(function ()
    {
        var id = this.id.replace('chk_', '');

        var status = $("#body tr[token='" + id + "']").find("td").eq(3).children(0).children(0).attr("status");

        if (status == 'true')
        {
            atLeastOne = true;
            setDateTimeSingleScale(id);
        }
    });

    if (!atLeastOne)
        Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + localizeSingleWord("scales", "scales_offline"));
}

function setDateTimeSingleScale(id)
{
    var date = datepicker.get('select', 'yyyy-mm-dd');
    var time = $("#timepicker").val();
    if (date == "" || time == "")
    {
        Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + localizeSingleWord("scales", "datetime_unset"));
        return;
    }

    if (firstAccess)
        // start preloader
        startPreloader();

    // send request
    {
        var baseUrl = window.sessionStorage.getItem("uriFM") + "/GROUPS/" + window.sessionStorage.getItem("groupId");
        var params = "";
        {
            params += "?id=" + id;
        }

        var data = { 'Date': date + 'T' + time }

        var settings = {
            async: true,
            cache: false,
            contentType: "application/json; charset=utf-8",
            crossDomain: true,
            data: JSON.stringify(data),
            dataType: "json",
            type: "POST",
            url: baseUrl + "/DATE" + params,
            timeout: 10000
        }

        $.ajax(settings).always(function (jqXHR)
        {
            if (firstAccess)
            {
                // end preloader
                endPreloader();
            }

            if (jqXHR.status == 200)
            {
                if (firstAccess)
                    firstAccess = false;

                // show a warning when server data is null
                Materialize.toast.reusable('<i class="mdi-cst-info"></i>' + localizeSingleWord("scales", "datetime_sent") + ' (id. ' + id + ')');
            }
            else
            {
                // show a warning when server data is null
                Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + jqXHR.statusText + ' [cod. ' + jqXHR.status + ']');
            }
        });
    }
}

