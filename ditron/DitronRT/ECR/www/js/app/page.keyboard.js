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
var display_retry_timeout;

// localize page (as <res></res> element)
localize('keyboard');

// start functions of the specific page
page_keyboard();

var count = 0;

// LOAD FUNCTION OF MODULE ITEM PAGE
function page_keyboard() 
{
    // end preloader
    endPreloader();

    // EVENTS
    //$('.keyb').unbind("click");
    //
    $(document).off('click', '.keyb');
    $(document).on('click', '.keyb', function () {
        var keycode = $(this).attr('id');
        SendKey(keycode);
    });
    TranslateKey();
   
    ReadDisplay();

    $.each( $(".verticalText"), function () { $(this).html($(this).text().replace(/(.)/g, "$1<br />")) } );
}

// SEND KEY
function SendKey(keycode) {

    // start preloader
    startPreloader();

    // send request
    {
        var baseUrl = WS_URL;

        // prepare json object data 
        var param = keycode;
        
        $.support.cors = true;
        var settings = {
            async: true,
            cache: true,
            contentType: "application/x-www-form-urlencoded; charset=utf-8",
            crossDomain: true,
            data: "keycode=" + param,
            // dataType: "json",
            type: "POST",
            //url: baseUrl + "/cgi-bin/ap1.fcrwra/cmd/presskey",
            url: baseUrl + "/cmd/keycode",
            timeout: 10000
        }

        $.ajax(settings).always(function (data, textStatus, jqXHR) 
        {
            // end preloader
            endPreloader();

            if (data.status == 200) 
            {
                // SUCCESS CASE
            }
            else 
            {
                // show a warning when server data is null
                Materialize.toast.reusable('<i class="mdi-cst-warning"></i>SendKey --> ' + jqXHR.statusText + ' [cod. ' + jqXHR.status + ']');
            }            
        });
    }
}

// Translate KEY
function TranslateKey() {

    // start preloader
    //startPreloader();

    // send request
    {
        var baseUrl = WS_URL;

        var webtab = document.getElementById("webkeyb");
        for(var i = 0; i < webtab.rows.length; i++) {
        
            var row = webtab.rows[i];
            count = -1;
            for(var j = 0; j < row.cells.length; j++) {

                var elem = row.cells[j].getElementsByClassName("keyb")[0];

                // prepare json object data 
                var param = elem.getAttribute('id');
                
                $.support.cors = true;
                var settings = {
                    async: false,
                    cache: false,
                    i : i,
                    j : j,
                    contentType: "application/x-www-form-urlencoded; charset=utf-8",
                    crossDomain: true,
                    data: "keycode=" + param,
                    // dataType: "json",
                    type: "GET",
                    //url: baseUrl + "/cgi-bin/ap1.fcrwra/cmd/presskey",
                    url: baseUrl + "/cmd/keycode",
                    timeout: 10000
                }

                $.ajax(settings).always(function (data, textStatus, jqXHR) 
                {
                    // end preloader
                    //endPreloader();

                    if (jqXHR.status == 200) 
                    {
                        // SUCCESS CASE

                        var defaultCode = data.Response_Data.defaultCode;
                        var alphaCode = data.Response_Data.alphaCode;
                        var controlCode = data.Response_Data.controlCode;
                        var testo;


                        if(defaultCode.search("kDepartment") != -1) {
                            testo = localizeSingleWord('keyboard', "kDepartment");
                            var numText = defaultCode.substring(11);
                            testo = testo + parseInt(numText);
                        }
                        else if(defaultCode.search("kSubTender") != -1) {
                            testo = localizeSingleWord('keyboard', "kSubTender");
                            var numText = defaultCode.substring(10);
                            testo = testo + ' ' + parseInt(numText);
                        }
                        else if(defaultCode.search("kLetter") != -1) {
                            testo = defaultCode.substring(7);
                        }
                        else if(defaultCode.search("kMacro") != -1) {
                            testo = localizeSingleWord('keyboard', "kMacro");
                            var numText = defaultCode.substring(6);
                            testo = testo + ' ' + parseInt(numText);
                        }
                        else {
                            testo = localizeSingleWord('keyboard', defaultCode);
                        }

                        var i = this.i;
                        var j = this.j;

                        var elem = document.getElementById("webkeyb").rows[i].cells[j].getElementsByClassName("keyb")[0];

                        if(typeof testo !== "undefined")
                            elem.textContent= testo;
                        else
                            elem.textContent = defaultCode;

                        if(defaultCode.search("kNumber") != -1 || defaultCode.search("kSeparator") != -1)
                            elem.className += ' grey darken-2';
                        else if(defaultCode.search("kSubtotalReferenceDrawer") != -1 || defaultCode.search("kTotal") != -1)
                            elem.className += ' lime darken-1';
                        else if(defaultCode.search("kKey") != -1)
                            elem.className += ' red darken-1';
                        else if(defaultCode.search("kClear") != -1)
                            elem.className += ' blue lighten-2';
                        else if(defaultCode.search("kDepartment") != -1)
                            elem.className += ' blue darken-2';
                        else
                            elem.className += ' grey';

                        elem.setAttribute('fcode', defaultCode);

                        count++;
                        
                        
                    }
                    else 
                    {
                        // show a warning when server data is null
                        Materialize.toast.reusable('<i class="mdi-cst-warning"></i>SendKey --> ' + jqXHR.statusText + ' [cod. ' + jqXHR.status + ']');
                    }            
                });

                if(j > 0 && i > 0) {
                     var prev_elem = row.cells[j-1].getElementsByClassName("keyb")[0];
                     var cspan = 1;
                     var rowhead = j;

                     if(elem.getAttribute("fcode") == prev_elem.getAttribute("fcode")){
                         rowhead--;
                         prev_elem = row.cells[rowhead];
                         elem = row.cells[j];

                         if(prev_elem.hasAttribute("rowhead"))
                            rowhead = parseInt(prev_elem.getAttribute("rowhead"));
                        prev_elem = row.cells[parseInt(rowhead)];
                         
                         if(prev_elem.hasAttribute("colspan"))
                             cspan = parseInt(prev_elem.getAttribute("colspan"));
                        
                        
                        cspan++;

                         elem.style.display = "none";
                         elem.setAttribute("rowhead", rowhead);
                         //row.cells.removeChild(elem);
                         prev_elem.setAttribute("colspan", cspan);
                         prev_elem.getElementsByClassName("keyb")[0].id = elem.getElementsByClassName("keyb")[0].id;
                         prev_elem.getElementsByClassName("keyb")[0].style.width = (cspan * 64 + (cspan)*1.5).toString()+"px";
                         
                     }

                     var cur_head = row.cells[rowhead];
                     if(i > 1) {
                         var colhead = i - 1;

                         prev_row =  webtab.rows[colhead];
                         if(prev_row.cells[rowhead].hasAttribute("colhead"))
                            colhead = parseInt(prev_row.cells[rowhead].getAttribute("colhead"));
                            
                        prev_row =  webtab.rows[colhead];

                         if(prev_row.cells[rowhead].getElementsByClassName("keyb")[0].getAttribute("fcode") == cur_head.getElementsByClassName("keyb")[0].getAttribute("fcode")) {
                            if((prev_row.cells[rowhead].hasAttribute("colspan") && prev_row.cells[rowhead].getAttribute("colspan") == cspan) ||
                            (!prev_row.cells[rowhead].hasAttribute("colspan") && 1 == cspan && !prev_row.cells[rowhead].hasAttribute("rowhead"))) {
                            
                                var rspan = 1;

                                
                                if(prev_row.cells[rowhead].hasAttribute("rowspan"))
                                    rspan = parseInt(prev_row.cells[rowhead].getAttribute("rowspan"));

                                rspan++;

                                cur_head.style.display = "none";
                                cur_head.setAttribute("colhead", colhead);
                                //row.cells.removeChild(elem);
                                prev_row.cells[rowhead].setAttribute("rowspan", rspan);
                                prev_row.cells[rowhead].getElementsByClassName("keyb")[0].id = cur_head.getElementsByClassName("keyb")[0].id;
                                prev_row.cells[rowhead].getElementsByClassName("keyb")[0].style.height = (rspan*64 + (rspan)*1).toString()+"px";
                             }
                        }
                     }
                }

                
            }
        }
    }
  
}

// SEND KEY
function ReadDisplay() {
    
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
            //data: "displaycode=" + param,
            // dataType: "json",
            type: "GET",
            //url: baseUrl + "/cgi-bin/ap1.fcrwra/cmd/display",
            url: baseUrl + "/cmd/display",
            timeout: 10000
        }

        $.ajax(settings).always(function (data, textStatus, jqXHR) {
            // end preloader
            //endPreloader();
			
		    // get data periodically
			clearTimeout(display_retry_timeout);
			display_retry_timeout = setTimeout(function () { ReadDisplay(); }, 100);

            if (jqXHR.status == 200) {
                // SUCCESS CASE
                var header = data.Response_Data.kHeader;
				var body = data.Response_Data.kBody;
				var trailer = data.Response_Data.kTrailer;
            
                // set input field
                var wLine = 16;
                
                $('#display').html(
                    GetFormattedLineDisplay(header.text, header.length, header.mode, header.fill, wLine) + 
                    GetFormattedLineDisplay(body.text, body.length, body.mode, body.fill, wLine) + 
                    GetFormattedLineDisplay(trailer.text, trailer.length, trailer.mode, trailer.fill, wLine));                

                // show success message
                //Materialize.toast.reusable('<i class="mdi-cst-info"></i>' + s);
                $("#signal").html('<i class="mdi-cst-info"></i>' + 'air ON');

            }
            else {
                // show a warning when server data is null
                //Materialize.toast.reusable('<i class="mdi-cst-warning"></i>ReadDisplay --> ' + jqXHR.statusText + ' [cod. ' + jqXHR.status + ']');
                $("#signal").html('<i class="mdi-cst-warning"></i>' + 'air OFF');
				
            }

        });
        
       
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


