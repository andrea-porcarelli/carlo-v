/*
 * @title
 * @description
 * @name 
 * 
 * @author Copyright 2017 Domenico Argenziano
 * @license 
 * @see 
 * @version 1.0.0.0
 */
var display_retry_timeout;

// localize page (as <res></res> element)
localize('telematicsettings');

// start functions of the specific page
page_settings();



// LOAD FUNCTION OF MODULE ITEM PAGE
function page_settings() 
{
    // end preloader
    endPreloader();

    $('select').material_select();
    // EVENTS
    $('.savebtn').unbind("click");
    $('.savebtn').on("click", function () {
        //var hostname = $(this).attr('id');
        var hostname = $("#hostname").val();
        var port = $("#port").val();
        var autosend = $("#autosend").val();
        SendSettings(hostname, port, autosend);
    });
    
    ReadSettings();

    $.each( $(".verticalText"), function () { $(this).html($(this).text().replace(/(.)/g, "$1<br />")) } );
}

// SEND KEY
function SendSettings(hostname, port, autosend) {

    // start preloader
    startPreloader();

    // send request
    {
        var baseUrl = WS_URL;

        // prepare json object data 
        var host_param = hostname;
        var port_param = port;
        var autosend_param = autosend;
        
        $.support.cors = true;
        var settings = {
            async: true,
            cache: false,
            contentType: "application/json; charset=utf-8",
            accept: "application/json",
            crossDomain: true,
            data: "Subset=Telematic&" + "TelematicServerHost=" + host_param + "&" + "TelematicServerPort=" + port_param + "&" + "TelematicAutoSend=" + autosend_param,
            // dataType: "json",
            type: "POST",
            url: baseUrl + "/cmd/settings",
            timeout: 10000
        }

        $.ajax(settings).always(function (data, textStatus, jqXHR) 
        {
            //alert(JSON.stringify(data));

            //alert(JSON.stringify(textStatus));

            // end preloader
            endPreloader();

            if (data.status == 200) 
            {
                // SUCCESS CASE
                ReadSettings();
            }
            else 
            {
                // show a warning when server data is null
                ReadSettings();
                Materialize.toast.reusable('<i class="mdi-cst-warning"></i>SendSettings --> ' + jqXHR.statusText + ' [cod. ' + jqXHR.status + ']');
            }            
        });
    }
}


function ReadSettings() {
    
    // start preloader
    //startPreloader();

    // send request
    {
        var baseUrl = WS_URL;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           

        // prepare json object data 
        //var param = displaycode;

        $.support.cors = true;
        var settings = {
            async: true,
            cache: false,
            contentType: "application/x-www-form-urlencoded; charset=utf-8",
            crossDomain: true,
            accept: "application/json",
            data: "Subset=Telematic",// + param,
            // dataType: "json",
            type: "GET",
            //url: baseUrl + "/cgi-bin/ap1.fcrwra/cmd/display",
            url: baseUrl + "/cmd/settings",
            timeout: 10000
        }

        $.ajax(settings).always(function (data, textStatus, jqXHR) {
            // end preloader
            //endPreloader();

            
            
            if (jqXHR.status == 200) {
                // SUCCESS CASE
                //var header = data.Response_Data.kHeader;
				var hostname = data.Response_Data.kHostname;
				var port = data.Response_Data.kPort;
				var autosend = data.Response_Data.kAutoSend;
				//var trailer = data.Response_Data.kTrailer;
				
                // set input field
                var wLine = 16;
                
                //$('#display').html(
                //    GetFormattedLineDisplay(header.text, header.length, header.mode, header.fill, wLine) + 
                //    GetFormattedLineDisplay(body.text, body.length, body.mode, body.fill, wLine) + 
                 //   GetFormattedLineDisplay(trailer.text, trailer.length, trailer.mode, trailer.fill, wLine));                
                 $("#hostname").val(hostname.text);
                 $("#port").val(port.text);
                 $("#autosend").val(autosend.text).material_select();
            
                // show success message
                //Materialize.toast.reusable('<i class="mdi-cst-info"></i>' + s);
                $("#signal").html('<i class="mdi-cst-info"></i>' + 'air ON');
            }
            else {
                // show a warning when server data is null
                //Materialize.toast.reusable('<i class="mdi-cst-warning"></i>ReadDisplay --> ' + jqXHR.statusText + ' [cod. ' + jqXHR.status + ']');
                $("#hostname").val('baaaaad');
                $("#signal").html('<i class="mdi-cst-warning"></i>' + 'air OFF');
            }

        });
        
        // get data periodically
        //clearTimeout(display_retry_timeout);
        //display_retry_timeout = setTimeout(function () { ReadSettings(); }, 500);
    }
}

function GetFormattedLineDisplay(text, length, mode, fill, wline)
{
    var res = '';
    var alignment = 'left';
    var padLeft = '&nbsp;';
    var padRight = '&nbsp;';

    //if (length > 0)
    {
        switch (mode)
        {
            case 'kLeft': 
                alignment = 'left';                 

                if (fill != ' ') {
                    padRight = fill.repeat(wline-length);
                }

                break;

            case 'kCenter': 
                alignment = 'center'; 

                if (fill != ' ') {
                    padLeft = fill.repeat((wline - length) /2);
                    padRight = fill.repeat(wline - length - padLeft.length);
                }
                break;            

            case 'kRight': 
                alignment = 'right'; 
                
                if (fill != ' ') {
                    padLeft = fill.repeat(wline-length);
                }
                break;
        }
    }    

    res = '<div style="text-align: ' + alignment + '">' + padLeft + text + padRight + '</div>';
    return res;
}


