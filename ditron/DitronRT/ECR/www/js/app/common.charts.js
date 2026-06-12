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

// ARRAY COLORS
var colors =
    [
        "#F44336", // 00
        "#E91E63", // 01
        "#9C27B0", // 02
        "#673AB7", // 03
        "#3F51B5", // 04
        "#2196F3", // 05
        "#03A9F4", // 06
        "#00BCD4", // 07
        "#009688", // 08
        "#4CAF50", // 09
        "#8BC34A", // 10
        "#CDDC39", // 11
        "#FFEB3B", // 12
        "#FFC107", // 13
        "#FF9800", // 14
        "#FF5722", // 15
        "#795548", // 16
        "#9E9E9E", // 17
        "#607D8B"  // 18
    ];

// ARRAY HIGHLIGHT COLORS
var hcolors =
    [
        "#EF9A9A", // 00
        "#F48FB1", // 01
        "#CE93D8", // 02
        "#B39DDB", // 03
        "#9FA8DA", // 04
        "#90CAF9", // 05
        "#81D4FA", // 06
        "#80DEEA", // 07
        "#80CBC4", // 08
        "#A5D6A7", // 09
        "#C5E1A5", // 10
        "#E6EE9C", // 11
        "#FFF59D", // 12
        "#FFE082", // 13
        "#FFCC80", // 14
        "#FFAB91", // 15
        "#BCAAA4", // 16
        "#EEEEEE", // 17 
        "#B0BEC5"  // 18
    ];

// CREATE NEW CHART OBJECT
function chartCreate()
{
    var moduleData = [];

    var canvas = document.getElementById('modular-chart');
    var chart = new Chart(canvas.getContext('2d')).PolarArea(
        moduleData,
        {
            // Boolean - Whether to animate the chart
            animation: false,

            //String - Animation easing effect
            animationEasing: "easeOutBounce",

            // Boolean - whether or not the chart should be responsive and resize when the browser does.
            responsive: true,

            // Boolean - Whether to show labels on the scale
            //scaleShowLabels: false,            

            // String - Template string for single tooltips
            tooltipTemplate: "<%if (label){%><%=label%>: <%}%><%=numeral(value).format('(0.00)')%>",

            //Boolean - Show line for each value in the scale
            scaleShowLine: true,
        });

    return chart;
}

// ADDING NEW ELEMENT INTO CHART OBJECT
function chartAdd(rowId, label, value)
{
    moduleChart.addData(
        {
            value: value,
            color: colors[rowId],
            highlight: highlight[rowId],
            label: label
        }, rowId);

    var helpers = Chart.helpers;

    // set click event on new element
    var legendHolder = document.getElementById('row' + rowId);
    helpers.addEvent(legendHolder, 'click', function ()
    {
        var activeSegment = moduleChart.segments[rowId];
        activeSegment.save();
        activeSegment.fillColor = activeSegment.highlightColor;
        moduleChart.showTooltip([activeSegment]);
        activeSegment.restore();
    });
}

function chartAddEvent(rowId, elementId) {
    var helpers = Chart.helpers;

    // set click event on new element
    var legendHolder = document.getElementById(elementId);
    helpers.addEvent(legendHolder, 'click', function () {
        var activeSegment = moduleChart.segments[rowId];
        activeSegment.save();
        activeSegment.fillColor = activeSegment.highlightColor;
        moduleChart.showTooltip([activeSegment]);
        activeSegment.restore();
    });
}

// UPDATING AN ELEMENT THAT ALREADY EXISTS
function chartUpd(rowId, label, value)
{
    if (moduleChart.segments.length > rowId)
    {
        moduleChart.segments[rowId].value = value;
        moduleChart.update();
        return true;
    } else {
        return false;
    }
}

// DELETING AN ELEMENT THAT ALREADY EXISTS [IN PROGRESS]
function chartDel(rowId)
{
    if (moduleChart.segments.length > rowId)
    {
        moduleChart.removeData(rowId);
        moduleChart.update();
    }
}

// CONVERTING HEXADECIMAL TO RGBA VALUE
function hex2rgba(hexStr, alpha)
{
    // note: hexStr should be #rrggbb
    var hex = parseInt(hexStr.substring(1), 16);
    var r = (hex & 0xff0000) >> 16;
    var g = (hex & 0x00ff00) >> 8;
    var b = hex & 0x0000ff;
    return "rgba(" + r + "," + g + "," + b + "," + alpha + ")";
}