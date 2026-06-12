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

// INCLUDE A JAVASCRIPT REFERENCE INTO PAGE
function IncludeJavaScript(jsFile)
{
    alert('include');
    $.getScript(jsFile);
    //document.write('<script type="text/javascript" src="' + jsFile + '"></scr' + 'ipt>');
}

