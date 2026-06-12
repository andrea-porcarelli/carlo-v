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
localize('networksettings');

// start functions of the specific page
page_settings();



// LOAD FUNCTION OF MODULE ITEM PAGE
function page_settings() 
{
    // end preloader
    endPreloader();

    $('select').material_select();
    // EVENTS
    $('.savebtn2').unbind("click");
    $('.savebtn2').on("click", function () {
        //var hostname = $(this).attr('id');
        var ipaddress = $("#ipaddress").val();
        var netmask = $("#netmask").val();
        var dhcp = $("#dhcp").val();
        var gateway = $("#gateway").val();
        var dns1 = $("#dns1").val();
        var dns2 = $("#dns2").val();
        SendSettings(dhcp, ipaddress, netmask, gateway, dns1, dns2);
    });
    
    ReadSettings();

    $.each( $(".verticalText"), function () { $(this).html($(this).text().replace(/(.)/g, "$1<br />")) } );
}

// SEND KEY
function SendSettings(dhcp, ipaddress, netmask, gateway, dns1, dns2) {

    // start preloader
    startPreloader();

    // send request
    {
        var baseUrl = WS_URL;

        // prepare json object data 
        var dhcp_param = dhcp;
        var ipaddress_param = ipaddress;
        var netmask_param = netmask;
        var gateway_param = gateway;
        var dns1_param = dns1;
        var dns2_param = dns2;
        
        $.support.cors = true;
        var settings = {
            async: true,
            cache: false,
            contentType: "application/json; charset=utf-8",
            accept: "application/json",
            crossDomain: true,
            data: "Subset=Network&" + "IPAddress=" + ipaddress_param + "&" + "DHCP=" + dhcp_param + "&" + "NetMask=" + netmask_param + "&" + "Gateway=" + gateway_param+ "&" + "DNS1=" + dns1_param + "&" + "DNS2=" + dns2_param,
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
            data: "Subset=Network", //+ param,
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
				var dhcp = data.Response_Data.kDHCP;
				var ipaddress = data.Response_Data.kIPAddress;
				var netmask = data.Response_Data.kSubnetMask;
				var gateway= data.Response_Data.kGateway;
				var dns1 = data.Response_Data.kDNS1;
				var dns2 = data.Response_Data.kDNS2;
				//var trailer = data.Response_Data.kTrailer;
				
                // set input field
                var wLine = 16;
                
                //$('#display').html(
                //    GetFormattedLineDisplay(header.text, header.length, header.mode, header.fill, wLine) + 
                //    GetFormattedLineDisplay(body.text, body.length, body.mode, body.fill, wLine) + 
                 //   GetFormattedLineDisplay(trailer.text, trailer.length, trailer.mode, trailer.fill, wLine));                
                 $("#gateway").val(gateway.text);
                 $("#ipaddress").val(ipaddress.text);
                 $("#dhcp").val(dhcp.text).material_select();
                 $("#netmask").val(netmask.text);
                 $("#dns1").val(dns1.text);
                 $("#dns2").val(dns2.text);
            
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


