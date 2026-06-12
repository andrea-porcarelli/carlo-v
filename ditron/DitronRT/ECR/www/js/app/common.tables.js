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

// HIGHLIGHT THE SPECIFIC TABLE ROW
function highlight(rowId, colorValue)
{
    if ($('#' + rowId).is(':visible'))
    {
        $('#' + rowId).effect('highlight', { color: colorValue }, 700);
    }
}

// SORT THE SPECIFIC UL LIST BY ATTRIBUTE
function listSort(listName, attributeName, asc, attributeIsNumber)
{
    var items = $(listName + ' li').get();
    items.sort(function (a, b)
    {
        var keyA = (attributeIsNumber) ? parseFloat($(a).attr(attributeName)) : $(a).attr(attributeName);
        var keyB = (attributeIsNumber) ? parseFloat($(b).attr(attributeName)) : $(b).attr(attributeName);

        if (keyA < keyB) return asc ? -1 : 1;
        if (keyA > keyB) return asc ? 1 : -1;
        return 0;
    });
    var ul = $(listName);
    $.each(items, function (i, li)
    {
        ul.append(li);
    });
}

// SORT THE SPECIFIC TABLE BY INDEX AND DIRECTION
function tableSort(gridName, index, direction)
{
    $(gridName).trigger('update');
    var sorting = [[index, direction]];
    $(gridName).trigger("sorton", [sorting]);
}

// LIMIT THE ROWS NUMBER INTO TABLE
function fixedRows(gridID, maxRows, syncChart)
{
    var counter = 0;
    $(gridID + " tr").each(function (index, row)
    {
        if ($(row).is(":visible"))
            counter++;

        if (counter > maxRows)
        {
            $(row).remove();

            if (syncChart == true)
            {
                //alert($(row).attr("id").match(/\d+/));
                var rowId = $(row).attr("id"); //.match(/\d+/);
                //alert(rowId);

                //alert(moduleChart.segments[rowId - 1].index);
                moduleChart.update();

                //chartDel(rowId);
            }
        }
    });
}

// CLEAR ALL ROWS
function clearAll(gridID, syncChart)
{
    var counter = 0;
    $(gridID + " tr").each(function (index, row)
    {
        $(row).remove();

        if (syncChart == true)
        {

        }
    });
}

// SETTING THE COLUMN's SIZE AND STYLE
function setSize(tr, gridHeaderID)
{
    // get header id
    var h = $(gridHeaderID + " tr:first").attr("id");

    $(tr).find('td').each(function (index, col)
    {

        // clone class 
        var c = $("#" + h + " th:eq(" + index + ")").attr("class");
        $(col).addClass(c);

        // clone style
        var s = $("#" + h + " th:eq(" + index + ")").css("width")
        $(col).css("width", s);
    });
}

// SELECTING OR DESELECTING ALL CHECKBOX 
function setSelectionAll(chkall, groupname)
{
    var selectAll = $(chkall).is(":checked");

    $('input[name="' + groupname + '"]').each(function ()
    {
        var ckbox = $("#" + this.id);
        ckbox.prop("checked", selectAll);
    });
}

// CHECK THE GLOBAL SELECTOR IF ALL CHECKBOXES ARE SELECTED OR DESELECTED
function setGlobalSelector(chkall, groupname)
{
    var selectedItems = $('input[name="' + groupname + '"]:unchecked').length;

    // uncheck the master checkbox
    $("#" + chkall).prop('checked', (parseInt(selectedItems) == 0));
}

function resetSelection(chkall, groupname)
{
    $("#" + chkall).prop('checked', false);

    $('input[name="' + groupname + '"]').each(function ()
    {
        var ckbox = $("#" + this.id);
        ckbox.prop("checked", false);
    });
}

