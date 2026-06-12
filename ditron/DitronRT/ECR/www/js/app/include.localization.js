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

/* FUNCTIONS DEVICE SESSION - BEGIN */
//document.addEventListener("deviceready", init, false);

function initLocalization() {
    console.log(navigator.globalization);
    //    navigator.globalization.getNumberPattern(
    //    function (pattern) {
    //        alert('pattern: ' + pattern.pattern + '\n' +
    //              'symbol: ' + pattern.symbol + '\n' +
    //              'fraction: ' + pattern.fraction + '\n' +
    //              'rounding: ' + pattern.rounding + '\n' +
    //              'positive: ' + pattern.positive + '\n' +
    //              'negative: ' + pattern.negative + '\n' +
    //              'decimal: ' + pattern.decimal + '\n' +
    //              'grouping: ' + pattern.grouping);
    //    },
    //    function () { alert('Error getting pattern\n'); },
    //    { type: 'currency' }
    //);
    navigator.globalization.getPreferredLanguage(
        function (language) {
            var languageCode = language.value.match(/([a-z]{2})-[A-Z]{2}/)[1];
            // success case
            //            window.localStorage.setItem("culture", language.value);
            var arrayLength = LANGUAGES.length;
            for (var i = 0; i < arrayLength; i++) {
                if (languageCode == LANGUAGES[i]) {
                    window.localStorage.setItem("language", languageCode);
                    window.localStorage.setItem("culture", language.value);
                    return;
                }
                //Do something
            }
            window.localStorage.setItem("language", DEF_LANGUAGE);
            window.localStorage.setItem("culture", DEF_CULTURE);
        },
        function () {
            // error case
            window.localStorage.setItem("language", DEF_LANGUAGE);
            window.localStorage.setItem("culture", DEF_CULTURE);
        });
}
/* FUNCTIONS DEVICE SESSION - END */

function numberToString(value, numberType, defer) {
    //if (device.platform != 'windows') {
    //    return value.toFixed(2);
    //}

    var formattedValue;
    var defaultType = 'decimal';
    if (numberType != null) {
        if (numberType == 'percent' && device.platform == 'windows') {
            value /= 100;
        }
        defaultType = numberType;
    }

    navigator.globalization.numberToString(value, function (res) {
        defer.resolve(res.value );
    }, function () {
        defer.resolve(value);
    }, { type: defaultType });
}

// TRANSLATING ALL <RES> ELEMENTS OF CURRENT PAGE
function localize() {
    // get dictionary 
    var xml = getDictionary("");

    if (typeof xml !== "undefined" && xml !== null && xml !== "") {
        // translate all (elements <res></res>)

        $("res").each(function () {
            //var key = $(this).attr('key');
            var key = $(this).text();
            var value = $(xml).find("add[key='" + key + "']").attr('value');

            $(this).html(value);
        });
    }
    else {
        Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + localizeSingleWord("common", "no_translated_file"));
    }
}

// TRANSLATING ALL <RES> ELEMENTS OF SPECIFIC PAGE
function localize(page) {
    // get dictionary
    var xml = getDictionary(page);

    if (typeof xml !== "undefined" && xml !== null && xml !== "") {
        // translate all (elements <res></res>)

        $("res").each(function () {
            
            //var key = $(this).attr('key');
            var key = $(this).text();
            var value = $(xml).find("add[key='" + key + "']").attr('value');

            $(this).html(value);
        });

        $("input[placeholder]").each(function () {
            var key = $(this).attr('placeholder');
            var value = $(xml).find("add[key='" + key + "']").attr('value');

            $(this).attr('placeholder', value);
        });

        $('body').find('*[localize]').each(function ()
        {
            var key = $(this).text();
            var value = $(xml).find("add[key='" + key + "']").attr('value');

            $(this).text(value);
        });
        /*
        $('body').find('*').each(function (index)
        {
            if ($(this).text() != '' && $(this).text().match("^&res;"))
            {
                var key = $(this).text().replace('&res;', '');
                var value = $(xml).find("add[key='" + key + "']").attr('value');

                $(this).html(value);
            }
        });
        */
    }
    else {
        Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + localizeSingleWord("common", "no_translated_file"));
    }
}

// TRANSLATING A SPECIFIC WORK (AS KEYWORD)
function localizeSingleWord(resourceName, keyword) {
    // get dictionary
    var xml = getDictionary(resourceName);

    if (typeof xml !== "undefined" && xml !== null && xml !== "") {
        if (typeof keyword !== "undefined" && keyword !== null && keyword !== "") {
            // translate all (elements <res></res>)
            return $(xml).find("add[key='" + keyword + "']").attr('value');
        }
    }
    else {
        Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + localizeSingleWord("common", "no_translated_file"));
    }
}

// GET DICTIONARY
function getDictionary(resourceName) {
    var res = "";

    if (typeof resourceName == "undefined" || resourceName == null || resourceName == "") {
        // get the current page name

        if (window.location.pathname == "/") {
            // if pathname is empty then page is "index"
            resourceName = "index";
        }
        else {
            // get page name from pathname 
            resourceName = window.location.pathname.match(/(\w*)[.]html/)[1];
        }

    }

    //window.localStorage.setItem("culture", "en-GB");

    // get stored "culture info" 
    var culture = getLanguage();

    // attempting to load the dictionary that match with user language
    $.ajax({
        async: false,
        type: "GET",
        url: "resources/" + resourceName + "." + culture + ".xml",
        dataType: "text",
        success: function (result) {
            res = result;
        },
        error: function (result) {
            // attempting to load the default dictionary 
            $.ajax({
                async: false,
                type: "GET",
                url: "resources/" + resourceName + "." + DEF_CULTURE + ".xml",
                dataType: "text",
                success: function (result) {
                    res = result;
                },
                error: function (result) {
                    return res;
                }
            });
        }
    });

    return res;
}

// GET LOCAL LANGUAGE 
function getLanguage() {
    var language = DEF_LANGUAGE;

    // get stored "culture info"
    if (window.localStorage !== undefined) {
        language = window.localStorage.getItem("language");
        if (language == null)
            language = DEF_LANGUAGE;
    }
    return language;
}

// GET LOCAL CULTURE 
function getCulture() {
    var culture = DEF_CULTURE;

    // get stored "culture info"
    if (window.localStorage !== undefined) {
        culture = window.localStorage.getItem("culture");
        if (culture == null)
            culture = DEF_CULTURE;
    }
    return culture;
}