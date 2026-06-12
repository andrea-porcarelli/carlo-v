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

//document.addEventListener('deviceready', onDeviceReady.bind(this), false);

//function onDeviceReady() {
//    // start functions of the specific page
//    initPushNotification();

//    // TODO: Cordova has been loaded. Perform any initialization that requires Cordova here.
//};

function unRegister(token) {
    var deviceType = 0;
    if (device.platform == "Android") {
        deviceType = 3;
    } else if (device.platform == "windows") {
        deviceType = 1;
    } else if (device.platform == "iOS") {
        deviceType = 2;
    }
    var param = { 'deviceUID': device.uuid, 'deviceType': deviceType }

    //// start preloader
    //startPreloader();

    // send request
    $.ajax({
        async: true,
        type: "POST",
        url: WS_URL1 + "/v1/Unregister",
        //data: "username=" + username + "&password=" + password,
        data: JSON.stringify(param),
        contentType: "application/json; charset=utf-8",
        dataType: "json",
        beforeSend: function (xhr) {
            // add basic authentication into request header
            xhr.setRequestHeader('Authorization', token);
        }
    }).done(function (data) {
    }).fail(function (data) {
    });
}
//PUSH NOTIFICATION
function initPushNotification() {
    PushNotification.hasPermission(function (data) {
        if (data.isEnabled) {
            console.log('isEnabled');
        }
    });
    var push = PushNotification.init({
        android: {
            senderID: "556084283671"
        },
        ios: {
            alert: "true",
            badge: "true",
            sound: "true"
        },
        windows: {}
    });    // prepare json object data 

    push.on('registration', function (data) {
        //Windows10 = 1,
        //iPhone = 2,
        //Android = 3,
        var deviceType = 0;
        console.log(data.registrationId);
        if (device.platform == "Android") {
            deviceType = 3;
        } else if (device.platform == "windows") {
            deviceType = 1;
        } else if (device.platform == "iOS") {
            deviceType = 2;
        }
        var param = { 'deviceUID': device.uuid, 'urlDevice': data.registrationId, 'deviceType': deviceType }

        //// start preloader
        //startPreloader();

        // send request
        $.ajax({
            async: true,
            type: "POST",
            url: WS_URL1 + "/v1/Register",
            //data: "username=" + username + "&password=" + password,
            data: JSON.stringify(param),
            contentType: "application/json; charset=utf-8",
            dataType: "json",
            beforeSend: function (xhr) {
                // add basic authentication into request header
                xhr.setRequestHeader('Authorization', window.localStorage.getItem("token"));
            }
        }).done(function (data) {
            //// end preloader
            //endPreloader();

            if (typeof data !== "undefined" && data !== null) {
                if (data[0].token == "") {
                    // show a warning when token is empty
                    Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + data.error.description, 15000);
                }
                else {
                    // save into local storage the received server authentication data

                    window.localStorage.setItem("registrationId", data);

                }
            }
        }).fail(function (data) {
            //// end preloader
            //endPreloader();

            // show a warning when server data is null
            Materialize.toast.reusable('<i class="mdi-cst-warning"></i>' + data.statusText + ' [' + data.status + ']', 15000);

        });
    });

    push.on('notification', function (data) {
         data.message,
         data.title,
         data.count,
         data.sound,
         data.image,
         data.additionalData
    });

    push.on('error', function (e) {
         e.message
    });
}

