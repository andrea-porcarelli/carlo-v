
window.amazonScannerSessions = window.amazonScannerSessions || {};
const sweetConfirm = (text, callback, willClose) => {
    swal(
        {
            title: translate("javascript.swal.confirm.title"),
            text: text,
            html:true,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: translate(
                "javascript.swal.confirm.confirmButtonText"
            ),
            cancelButtonText: translate(
                "javascript.swal.confirm.cancelButtonText"
            ),
            customClass: {
                confirmButton: "btn btn-success",
                cancelButton: "btn btn-outline-danger ms-1",
            },
            buttonsStyling: false,
            willClose: () => {
                if (willClose !== undefined) {
                    willClose();
                }
            },
        },
        function (result) {
            if (result) {
                swal.close();
                callback();
            } else {
                if (willClose !== undefined) {
                    willClose();
                }
            }
        }
    );
};

const sweetInput = (title, text, callback, label) => {
    swal({
            title,
            text,
            type: "input",
            inputValue: label,
            showCancelButton: true,
            closeOnConfirm: false,
            animation: "slide-from-top",
        },
        (value)=> {
            callback(value)
        }
    );
}

const ajax = (params) => {
    return new Promise((resolve, reject) => {
        $.ajax(params.path, {
            data: params.data,
            method: params.method ? params.method : "post",
            dataType: params.dataType ? params.dataType : "json",
        })
            .then((response) => resolve(response))
            .catch((errors) => reject(errors));
    });
};

const serialize = (el) => {
    let form = $(`${el}`);
    let serialize = form.serializeArray();
    serialize = serialize.concat(
        $(`${el} input[type=checkbox]:not(:checked)`)
            .map(function () {
                return { name: this.name, value: "0" };
            })
            .get()
    );
    serialize = serialize.concat(
        $(`${el} input[type=radio]`)
            .map(function () {
                return { name: this.name, value: this.value };
            })
            .get()
    );
    serialize.forEach((item) => {
        if (item.value === "-1") {
            let element = serialize.find(({ name }) => name === item.name);
            element.value = "";
        }
        const classes = form.find(`[name*='${item.name}']`).attr('class');
        if (classes !== undefined && classes.indexOf('summernote') >= 0) {
            const field = form.find(`[name*='${item.name}']`);
            item.value = field.summernote('code');
        }
    });
    return { data: serializeObject(serialize), form: form };
};

const serializeObject = (obj) => {
    let jsn = {};
    $.each(obj, function () {
        if (jsn[this.name]) {
            if (!jsn[this.name].push) {
                jsn[this.name] = [jsn[this.name]];
            }
            jsn[this.name].push((this.value === "on" ? "1" : this.value) || "");
        } else {
            jsn[this.name] = this.value === "on" ? "1" : this.value || "";
        }
    });
    return jsn;
};

const clearForm = (form, disable, responseDiv) => {
    if (disable) {
        form.find(":input").prop("disabled", true);
    } else {
        form.find(":input").prop("disabled", false);
    }
    form.removeClass("was-validated");
    const invalid = form.find(".invalid-feedback");
    invalid.html("");
    invalid.parent().find("input, select").removeClass("is-invalid");
    if (responseDiv) {
        $(responseDiv.element).removeClass(function (index, className) {
            return (className.match(/(^|\s)alert-\S+/g) || []).join(" ");
        });
        $(responseDiv.element)
            .removeClass("hide")
            .addClass(`alert ${responseDiv.class}`)
            .html(responseDiv.message);
    }
};

const renderErrors = (errors, form) => {
    if (errors.responseJSON.error === undefined) {
        let items = errors.responseJSON.errors;
        if ( items !== undefined && items.length === 0 && (errors.responseJSON.error || errors.responseJSON.message)) {
            sweet(errors.responseJSON.error ?? errors.responseJSON.message);
        } else {
            for (let item in items) {
                if (item.length > 0) {
                    let message = "";
                    if (items[item].length > 1) {
                        let rows = items[item];
                        for (let row in rows) {
                            message += `${rows[row]} <br />`;
                        }
                    } else {
                        message = items[item];
                    }
                    if (item.match(/\./)) {
                        let items = item.split(".");
                        if (items.length > 1) {
                            item = `${items[0]}[${items[1]}][${items[2]}]`;
                        } else {
                            item = `${items[0]}[${items[1]}]`;
                        }
                    }
                    $(document).find(`input[name='${item}']`)
                        .addClass("is-invalid")
                        .parent()
                        .find(".invalid-feedback")
                        .show()
                        .html(message);
                    $(document).find(`select[name='${item}']`)
                        .addClass("is-invalid")
                        .parent()
                        .find(".invalid-feedback")
                        .show()
                        .html(message);
                    $(document).find(`textarea[name='${item}']`)
                        .addClass("is-invalid")
                        .parent()
                        .find(".invalid-feedback")
                        .show()
                        .html(message);
                }
            }
        }
    } else {
        sweet(errors.responseJSON.error ?? errors.responseJSON.message);
    }
    setTimeout(() => {
        $(document).find('.invalid-feedback').html('')
        $(document).find('.invalid-feedback').parent().find("input, select, textarea").removeClass("is-invalid");
    }, 5000)
};

const sweet = (text, title, type, callback) => {
    swal(
        {
            title: title !== undefined ? title : translate("javascript.swal.error-title"),
            text: text,
            type: type !== undefined ? type : "warning",
            showCancelButton: false,
            closeOnConfirm: true,
        },
        function (result) {
            if (result && callback) {
                callback();
            }
        }
    );
};

const translate = (string, args) => {
    let value = _.get(window.i18n, string);
    if (args) {
        _.forEach(args, (paramVal, paramKey) => {
            value = lodash.replace(value, `:${paramKey}`, paramVal);
        });
    }
    return value;
};

const getTag = (html, selector) => {
    let parser = new DOMParser();
    let dom = parser.parseFromString(html, "text/html");
    let elems = dom.querySelectorAll(selector);
    return Array.prototype.map.call(elems, function (e) {
        return e.outerHTML.replace(/<\/?[^>]+(>|$)/g, "");
    });
};

const removeHtml = (text) => {
    let values = getTag(text, ".hidden-value");
    if (values) {
        return values[0];
    }
    return "";
};

const actionDatatable = (dt, button, type) => {
    let tableId;
    dt.one("preXhr", function (e, s, data) {
        tableId = s.sTableId;
        $(`#${tableId}`).prepend(
            '<div class="overlay"><span>Attendi...</span></div>'
        );
        data.length = -1;
    })
        .one("draw", function (e) {
            let buttonConfig = $.fn.DataTable.ext.buttons[type];
            $.extend(true, buttonConfig, {});
            buttonConfig.action(e, dt, button, buttonConfig);
            dt.one("xhr", function (e, s, data) {
                data.length = 50;
            }).draw();
            $(`#${tableId}`).find(".overlay").remove();
        })
        .draw();
};

const datatable = (params) => {
    let datatable_table = [];
    let datatables = [];
    let name =
        typeof params.name !== "undefined" ? params.name : "datatable_table";
    datatable_table[name] = $(`.${name}`);
    if (datatable_table[name].length) {
        let exportRules = {
            exportOptions: {
                columns: ":not(:last-child)",
                format: {
                    body: function (text, column) {
                        return column >= 8 ? removeHtml(text) : text;
                    },
                },
                modifier: {
                    order: "current",
                    page: "all",
                },
            },
        };
        let exportExtend = [];
        if (params.export) {
            params.export.forEach((item) => {
                if (item === "csv") {
                    exportExtend.push(
                        $.extend(true, {}, exportRules, {
                            extend: "csvHtml5",
                            action: function (e, dt, button, config) {
                                actionDatatable(dt, button, "csvHtml5");
                            },
                        })
                    );
                }
                if (item === "excel") {
                    exportExtend.push(
                        $.extend(true, {}, exportRules, {
                            extend: "excelHtml5",
                            text: '<span class="fa fa-file-excel-o"></span> Excel Export',
                            action: function (e, dt, button) {
                                actionDatatable(dt, button, "excelHtml5");
                            },
                        })
                    );
                }
            });
        }
        datatables[name] = datatable_table[name].DataTable({
            ajax: {
                url: params.url,
                data: function (d) {
                    let filters = {};
                    if (typeof params.dataForm !== "undefined") {
                        if (params.dataForm.length > 0) {
                            params.dataForm.forEach((item) => {
                                let element = $(`${params.search_class === undefined ? '.advanced-search' : params.search_class } .${item}`);
                                let val = element.val();
                                if (element.attr("type") === "checkbox") {
                                    if (element.is(":checked")) {
                                        val = "1";
                                    } else {
                                        val = "";
                                    }
                                }
                                filters[item] = val;
                            });
                            if (params.saveFilters !== 'undefined' && params.saveFilters) {
                                Cookies.set('filters', filters);
                            }
                        }
                    }
                    d.filters = filters;
                },
            },
            processing: true,
            serverSide: true,
            columns: params.columns,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"],
            ],
            iDisplayLength:
                typeof params.iDisplayLength !== "undefined"
                    ? params.iDisplayLength
                    : 50,
            order:
                typeof params.order !== "undefined"
                    ? params.order
                    : [[0, "desc"]],
            bStateSave:
                typeof params.stateSave !== "undefined"
                    ? params.stateSave
                    : false,
            dom:
                '<"d-flex justify-content-between align-items-center mx-0 row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 text-right"' +
                (typeof params.searchBar !== "undefined" &&
                params.searchBar === false
                    ? ""
                    : "f") +
                ">>t" +
                (typeof params.export !== "undefined" ? "B" : "") +
                '<"d-flex justify-content-between mx-0 row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            buttons: [exportExtend],
            rowGroup:
                typeof params.grouping !== "undefined"
                    ? { dataSrc: params.grouping }
                    : null,
            orderCellsTop: true,
            responsive: true,
            language: {
                loadingRecords: "&nbsp;",
                processing:
                    '<i class="fa fa-spinner fa-spin fa-3x fa-fw"></i><span class="sr-only">Loading...</span> ',
                search: translate("javascript.datatable.search"),
                emptyTable: translate("javascript.datatable.emptyTable"),
                info: translate("javascript.datatable.info"),
                infoEmpty: translate("javascript.datatable.infoEmpty"),
                lengthMenu: translate("javascript.datatable.lengthMenu"),
                infoFiltered: translate("javascript.datatable.search"),
                paginate: {
                    // remove previous & next text from pagination
                    previous: "&nbsp;",
                    next: "&nbsp;",
                },
            }
        });
    }

    $("input.dt-input").on("keyup", function () {
        filterColumn($(this).attr("data-column"), $(this).val());
    });
};

const filterColumn = (i, val) => {
    if (i === 5) {
        var startDate = $(".start_date").val(),
            endDate = $(".end_date").val();
        if (startDate !== "" && endDate !== "") {
            filterByDate(i, startDate, endDate); // We call our filter function
        }
        $(".dt-advanced-search").dataTable().fnDraw();
    } else {
        $(".dt-advanced-search")
            .DataTable()
            .column(i)
            .search(val, false, true)
            .draw();
    }
};

const filterByDate = (column, startDate, endDate) => {
    $.fn.dataTableExt.afnFiltering.push(function (
        oSettings,
        aData,
        iDataIndex
    ) {
        var rowDate = normalizeDate(aData[column]),
            start = normalizeDate(startDate),
            end = normalizeDate(endDate);

        // If our date from the row is between the start and end
        if (start <= rowDate && rowDate <= end) {
            return true;
        } else if (rowDate >= start && end === "" && start !== "") {
            return true;
        } else if (rowDate <= end && start === "" && end !== "") {
            return true;
        } else {
            return false;
        }
    });
};

const normalizeDate = function (dateString) {
    let date = new Date(dateString);
    return (
        date.getFullYear() +
        "" +
        ("0" + (date.getMonth() + 1)).slice(-2) +
        "" +
        ("0" + date.getDate()).slice(-2)
    );
};

const reloadTable = (name = '.datatable_table') => {
    const table = $(name).DataTable();
    table.clear();
    table.ajax.reload();
};

const debounce = (func, wait, immediate) => {
    let timeout;
    return function () {
        let context = this,
            args = arguments;
        let later = function () {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        let callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
};

const loadSelect2 = (parameters) => {
    setTimeout(() => {
        $(`${parameters.element}`).select2({
            placeholder: "Cerca",
            closeOnSelect: parameters.closeOnSelect !== undefined ? parameters.closeOnSelect : false,
            ajax: {
                url: `/${parameters.route}`,
                dataType: "json",
                delay: 250,
                method:
                    parameters.method !== undefined ? parameters.method : "get",
                data: (params) => {
                    return {
                        search: params.term,
                        role: (parameters.role !== undefined) ? parameters.role : '',
                        fields: (parameters.fields !== undefined) ? parameters.fields : '',
                        is_active: (parameters.is_active !== undefined) ? parameters.is_active : '',
                        brand_id: (parameters.brand_id !== undefined) ? parameters.brand_id : '',
                    };
                },
                processResults: function (data) {
                    return {
                        results: $.map(data.results, function (item) {
                            return {
                                text: item.text,
                                id: item.id,
                            };
                        }),
                    };
                },
                cache: false,
            },
        });
    }, 350);
};

const removeDropzone = () => {
    if (Dropzone.instances.length > 0) {
        Dropzone.instances.forEach((e) => {
            e.off();
            e.destroy();
        });
    }
};

const logs = () => {
    ajax({ path: "logs/get", method: "get" }).then((response) => {
        $(".logs").html(response);
    });
};

const loadSwitch = (container) => {
    let elems = Array.prototype.slice.call(
        document.querySelectorAll(`${container} .js-switch`)
    );
    elems.forEach(function (html, number) {
        let el = $(`${container} .js-switch`).eq(number);
        if (el.parent().find("span.switchery").length === 0) {
            Switchery(html);
        }
    });
};

const changeStatusObject = (el) => {
    const elementId = el.data("id");
    const model = el.data("model");
    ajax({ path: `/${model}/${elementId}/status`, method: "post" }).then(
        () => {
            reloadTable();
        }
    );
};

const dashboard = () => {
    const data = App.serialize('.load-dashboard');
    console.log(data)
    App.ajax({path: `/dashboard_ajax`, method: 'post', data: { ...data.data }}).then(response => {
        $('.dashboard-report').html(response.html)
    });
}

const success = (button) => {
    button.parent().parent().append(`<div class="col-xs-12 m-t-sm"><div class="alert alert-success"><span class="fa fa-check-circle text-info"></span><div class='text-info'>Operazione effettuata con successo</div></div></div>`)
    setTimeout(() => {
        button.parent().parent().find('.response-message').html('');
    }, 1500)
}

const update_or_create = (button, method, form_name, endpoint, redirect, callback, element) => {
    const data = App.serialize(form_name);
    $(form_name).append('<div class="overlay"><span>Attendi...</span></div>')
    const form = $(null, element);
    App.ajax({ path: `${endpoint}`, method, data: { ...data.data }}).then(response => {
        if (callback === undefined) {
            App.success(button);
        }
        $(form_name).find('.overlay').remove()
        if (redirect !== null) {
            if (response.url !== undefined) {
                redirect = response.url;
            }
            setTimeout(() => {
                window.location.href = redirect;
            }, 1500)
        }
        if (callback !== undefined && typeof callback == 'function') {
            callback(response);
        }
    }).catch(errors => {
        console.log(errors)
        $(form_name).find('.overlay').remove();
        App.renderErrors(errors, form)
        App.sweet(errors.responseJSON.message);
    })
}

const initDropzone = (class_name, callback, acceptedFiles = '.pdf', uploadMultiple = false, maxFiles = 1) => {
    $(`.${class_name}`).dropzone({
        uploadMultiple,
        maxFiles,
        acceptedFiles,
        init: function () {
            this.on("error", function (file, errorMessage) {
                App.sweet(errorMessage.file ?? errorMessage.message);
                this.removeFile(file);
            }).on("complete", function (file, message) {
                callback(file, message, this);
            });
        },
    });
};

const upload = (parameters) => {
    const callback = parameters.callback;
    App.initDropzone(parameters.class, (file, message) => {
        callback(file, message)
    }, parameters.acceptedFiles, parameters.multiple, parameters.maxFiles)
}

const preview_image = (parameters) => {
    $(parameters.container).parent().parent().append(`<div class="col-xs-12 upload-preview"><img src="${parameters.file}" /></div>`);
}

const append_form = (parameters) => {
    $(`.form-element`).append(`<input type="hidden" name="${parameters.name}" value="${JSON.stringify(parameters.file).replace(/[\/\(\)\']/g, "\\$&")}" />`);
}

const filter_elements = (query, container, element) => {
    $(container).find(element).each(function () {
        if ($(this).text().toLowerCase().trim().indexOf(query.toLowerCase()) === -1) {
            $(this).hide();
        } else {
            $(this).show();
        }
    });
}

const delete_media = (e) => {
    App.sweetConfirm("Sei sicuro di voler rimuovere questo file?", () => {
        const id = e.data('id');
        App.ajax({ path: `/media/${id}`, method: 'delete'}).then(() => {
            $(`.media_${id}`).remove();
        })
    })
}

const date_range_picker = (parameters) => {
    console.log(parameters)
    $(`${parameters.handler}`).daterangepicker({
        minDate: parameters.minDate !== undefined ? parameters.minDate : new Date(),
        format: "DD/MM/YYYY",
        locale: {
            "separator": " - ",
            "applyLabel": "Applica",
            "cancelLabel": "Annulla",
            "fromLabel": "Dal",
            "toLabel": "Al",
            "customRangeLabel": "Custom",
            "weekLabel": "S",
            "daysOfWeek": [
                "Do",
                "Lu",
                "Ma",
                "Me",
                "Gi",
                "Ve",
                "Sa"
            ],
            "monthNames": [
                "Gennaio",
                "Febbraio",
                "Marzo",
                "Aprile",
                "Maggio",
                "Giugno",
                "Luglio",
                "Agosto",
                "Settembre",
                "Ottobre",
                "Novembre",
                "Dicembre"
            ],
            "firstDay": 1
        },
    });
}

const plus_minus = (e) =>  {
    const $button = e;
    const $input = $button.closest('.plus-minus-quantity').find("input.quantity-input");
    $input.val((i, v) => Math.max(0, +v + 1 * $button.data('multi')));
}

const open_modal = (e) =>  {
    const modal = $(`#${e.data('modal')}`);
    const path = e.data('route');
    App.ajax({ path , method: 'get' }).then(response => {
        modal.find('.modal-body').html(response.html)
        modal.modal('show');
    })
}
const selectChoice = (parameters) => {
    const select = document.getElementById(parameters.id);
    const choices = new Choices(select, {
        placeholder: true,
        searchEnabled: true,
        shouldSort: false,
        searchChoices: false, // Disattiva la ricerca locale
        loadingText: 'Caricamento...',
        noResultsText: 'Nessun risultato',
        itemSelectText: 'Seleziona',
        allowHTML: true
    });

// Intercetta il termine digitato
    select.addEventListener('search', function (event) {
        const searchTerm = event.detail.value;

        // Mostra "loading" mentre cerca
        choices.clearChoices();
        choices.setChoices([{ label: 'Caricamento...', value: '', disabled: true }], 'value', 'label', false);
        const body = parameters.body;
        body.value = searchTerm;
        // Chiamata AJAX
        fetch(`/${parameters.path}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf_token
            },
            body:  JSON.stringify(body)
        })
            .then(response => response.json())
            .then(data => {
                const searchTerm = event.detail.value.toLowerCase();
                const highlight = (text) => {
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    return text.replace(regex, '<mark>$1</mark>'); // evidenziamo con <mark>
                };
                // Prepara i dati per Choices
                const results = data.results.map(item => {
                    return {
                        value: item.id, // cambia secondo la tua struttura
                        label: `${highlight(item.text)}`
                    };
                });

                choices.setChoices(results, 'value', 'label', true);
            })
            .catch(() => {
                choices.setChoices([], 'value', 'label', true);
            });
    });
}

const open_scan_barcode = (e) => {
    const product_id = e.data("id");
    const modal = $("#scan-qr-code");

    // Store scanner instance globally
    let html5QrcodeScanner = null;

    // Initialize alert manager for this scanner session
    const alertManager = {
        activeAlerts: new Map(),
        cooldownPeriod: 10000, // 10 seconds
        baseDisplayDuration: 3000, // 3 seconds base display time

        showAlert(message, type) {
            const now = Date.now();
            const alertKey = `${type}:${message}`;

            // Check if this alert is already active
            if (this.activeAlerts.has(alertKey)) {
                const alertData = this.activeAlerts.get(alertKey);

                // Check if in cooldown period
                if (now - alertData.lastShown < this.cooldownPeriod) {
                    // Extend the life of existing alert if still visible
                    if (alertData.element && alertData.element.is(':visible')) {
                        // Clear existing timeout
                        if (alertData.timeout) {
                            clearTimeout(alertData.timeout);
                        }

                        // Flash effect to show it's been triggered again
                        alertData.element.css('opacity', '0.7').animate({opacity: 1}, 200);

                        // Set new timeout
                        alertData.timeout = setTimeout(() => {
                            alertData.element.fadeOut(() => {
                                alertData.element.remove();
                                this.activeAlerts.delete(alertKey);
                            });
                        }, this.baseDisplayDuration);

                        // Update last shown time
                        alertData.lastShown = now;
                    }
                    return; // Don't create new alert
                }
            }

            // Create new alert
            const alertClass = type === 'success' ? 'alert-success' :
                type === 'warning' ? 'alert-warning' : 'alert-info';

            const alertElement = $(`
				<div class="scanner-alert ${alertClass}">
					<i class="fa fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
					${message}
				</div>
			`);

            // Add to container below camera
            let alertContainer = $('.scanner-alerts-container');
            if (alertContainer.length === 0) {
                // Create container if it doesn't exist
                alertContainer = $('<div class="scanner-alerts-container"></div>');
                $('#reader').after(alertContainer);
            }

            alertContainer.append(alertElement);
            alertElement.fadeIn(200);

            // Set timeout for removal
            const timeout = setTimeout(() => {
                alertElement.fadeOut(() => {
                    alertElement.remove();
                    this.activeAlerts.delete(alertKey);
                });
            }, this.baseDisplayDuration);

            // Store alert data
            this.activeAlerts.set(alertKey, {
                element: alertElement,
                lastShown: now,
                timeout: timeout
            });
        },

        clearAll() {
            // Clear all active alerts and timeouts
            this.activeAlerts.forEach((alertData) => {
                if (alertData.timeout) {
                    clearTimeout(alertData.timeout);
                }
                if (alertData.element) {
                    alertData.element.remove();
                }
            });
            this.activeAlerts.clear();
        }
    };

    // Make alertManager accessible to nested functions
    window.currentAlertManager = alertManager;

    // Initialize or get the scannedCodes array for this specific event
    window.amazonScannerSessions[product_id] = window.amazonScannerSessions[product_id] || [];

    // Get existing values and populate the event-specific array
    const existingSerials = $(`.activity-product-${product_id}`).val();
    if (existingSerials) {
        window.amazonScannerSessions[product_id] = existingSerials.split(',').filter(s => s.trim());
    } else {
        window.amazonScannerSessions[product_id] = [];
    }

    // Use local reference for convenience
    let scannedCodes = window.amazonScannerSessions[product_id];

    // Create enhanced modal content with integrated header and alert container
    const modalContent = `
		<div class="scanner-container">
			<div class="scanner-body">
				<div id="reader" class="scanner-reader"></div>

				<!-- Alert container positioned below camera -->
				<div class="scanner-alerts-container"></div>

				<div class="scanned-list-container">
					<div class="scanned-list-header">
						<h6><i class="fa fa-list"></i> Seriali Scansionati:</h6>
					</div>
					<div class="scanned-list" id="scanned-list-${product_id}">
						${renderScannedList(scannedCodes, product_id)}
					</div>
				</div>
			</div>
		</div>
	`;

    // Create custom header to replace modal's default header
    const customHeader = `
		<div class="scanner-modal-header">
			<div class="scanner-header-content">
				<h5 class="scanner-title">
					<i class="fa fa-qrcode"></i> Scanner QR CODE
				</h5>
			</div>
			<button type="button" class="close" data-dismiss="modal">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
	`;

    // Hide default modal header and replace with custom one
    modal.find('.modal-header').hide();

    // Remove any existing custom header before adding new one
    modal.find('.scanner-modal-header').remove();

    // Add the new custom header
    modal.find('.modal-content').prepend(customHeader);
    modal.find(".modal-body").empty().html(modalContent);
    modal.modal("show");

    // Initialize scanner with delay
    setTimeout(() => {
        html5QrcodeScanner = new Html5QrcodeScanner(
            "reader",
            {
                fps: 10,
                qrbox: { width: 200, height: 200 },
                supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
                aspectRatio: 1.0,
                showTorchButtonIfSupported: true
            },
            false
        );

        html5QrcodeScanner.render(onScanSuccess, onScanError);
    }, 300);

    // Success callback
    function onScanSuccess(decodedText) {
        // Get the current array for this event
        const currentCodes = window.amazonScannerSessions[product_id] || [];

        // Check for duplicates in this event's array
        if (currentCodes.includes(decodedText)) {
            alertManager.showAlert('Seriale già scansionato!', 'warning');
            return;
        }

        // Add to this event's array
        currentCodes.push(decodedText);
        window.amazonScannerSessions[product_id] = currentCodes;

        updateScannedList(currentCodes, product_id);
        updateFields(currentCodes, product_id);

        // Success feedback
        alertManager.showAlert('Seriale aggiunto con successo!', 'success');
    }

    // Error callback
    function onScanError(errorMessage) {
        // Silent fail for continuous scanning
    }

    // Modal cleanup on close
    modal.on('hidden.bs.modal', function() {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
        }
        // Clear all alerts and timeouts
        alertManager.clearAll();
        // DON'T delete the event-specific scanner data
        // Let it persist so deleted serials stay deleted
        modal.find(".modal-body").empty();
        modal.off('hidden.bs.modal');
        // Clean up global reference
        window.currentAlertManager = null;
    });
};


// Helper function to render scanned list
function renderScannedList(codes, event_id) {
    if (codes.length === 0) {
        return '<div class="empty-list">Nessun seriale scansionato</div>';
    }

    let html = '<ul class="scanned-items-list">';
    codes.forEach((code, index) => {
        html += `
			<li class="scanned-item" data-index="${index}">
				<span class="serial-code">
					<i class="fa fa-barcode"></i> ${code}
				</span>
				<button type="button" class="btn btn-xs btn-danger remove-serial"
						onclick="removeSerial('${event_id}', ${index})">
					<i class="fa fa-trash"></i>
				</button>
			</li>
		`;
    });
    html += '</ul>';
    return html;
}

// Update the list display
function updateScannedList(codes, event_id) {
    $(`#scanned-list-${event_id}`).html(renderScannedList(codes, event_id));
    $('.scanned-count').text(codes.length);
}

// Update input fields
function updateFields(codes, event_id) {
    // Update serial input (comma-separated)
    $(`.activity-product-${event_id}`).val(codes.join(','));
}

// Remove serial from list
window.removeSerial = function(event_id, index) {
    // Get current codes from input
    const existingSerials = $(`.activity-product-${event_id}`).val();
    let scannedCodes = existingSerials ? existingSerials.split(',').filter(s => s.trim()) : [];

    // Remove the item
    scannedCodes.splice(index, 1);

    // Update the event-specific scanner array if it exists
    if (window.amazonScannerSessions && window.amazonScannerSessions[event_id]) {
        window.amazonScannerSessions[event_id] = scannedCodes;
    }

    // Update everything
    updateScannedList(scannedCodes, event_id);
    updateFields(scannedCodes, event_id);

    // Use the alert manager if available (when modal is open)
    if (window.currentAlertManager) {
        window.currentAlertManager.showAlert('Seriale rimosso', 'info');
    }
};



const openAddDynamicModal = (el, callback) => {
    const modal = $("#dynamic-modal");
    const model = el.data("model");
    const objectId = el.data("id");
    const route = el.data("route");
    let params = { path: `/${model}/create`, method: "get" };
    if (typeof objectId !== "undefined") {
        params = { path: `/${model}/${objectId}`, method: "get" };
    }if (typeof route !== "undefined") {
        params = { path: `/${route}`, method: "get" };
    }
    App.ajax(params).then((response) => {
        modal.find(".modal-body").html(response.html);
        modal.modal("show");
        if (callback) {
            callback();
        }
    });
};

const format_price = (value, decimals = 2) => {
    if (isNaN(value)) return '0,00 €';

    return parseFloat(value).toLocaleString('it-IT', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }) + ' €';
}

const init = () => {

    $(document).on("click", ".btn-status", function () {
        changeStatusObject($(this));
    });

    $(document).on("datatable", function (e, parameters) {
        datatable(parameters);
    });

    $(document).on("startSelect2", function (e, parameters) {
        loadSelect2(parameters);
    });

    $(document).on("loadSwitchTrigger", function (e, parameters) {
        loadSwitch(parameters.container)
    });

    $(document).on("reloadDatatable", function (e, parameters) {
        reloadTable()
    });

    $(document).on("click", ".btn-find", function () {
        reloadTable();
    });

    $(document).on("blur keyup", ".advanced-search input",
        debounce(function () {
            if ($(this).val().length === 0 || $(this).val().length > 2) {
                reloadTable();
            }
        }, 500)
    );

    $(document).on(
        "change",
        ".advanced-search select, .advanced-search input",
        function () {
            reloadTable();
        }
    );

    if ($(".logs").length > 0) {
        logs();
        $(".reload-logs").on("click", function () {
            logs();
        });
    }

    if ($(".dashboard-report").length > 0) {
        dashboard();
        $(".load-dashboard-ajax").on("click", function () {
            dashboard();
        });
    }

    $(document).on("keyup blur", ".is_number", function () {
        const text = $(this);
        text.val(text.val().toString().replace(/,/g, "."));
    });

    $(document).on('click', '.quantity-right-plus', function() {
        const input = $(this).parent().parent().find('.input-number');
        const max = input.data('max');
        const quantity = parseInt(input.val());
        if (quantity < max) {
            $(this).parent().parent().find('.input-number').val(quantity + 1);
        }
    });

    $(document).on('click', '.quantity-left-minus', function(e){
        const input = $(this).parent().parent().find('.input-number');
        const quantity = parseInt(input.val());
        if(quantity > 0){
            $(this).parent().parent().find('.input-number').val(quantity - 1);
        }
    });

    $(document).on("filter_elements",  function (e, parameters) {
        filter_elements(parameters.query, parameters.container, parameters.element)
    });

    $(document).on("upload",  function (e, parameters) {
        upload(parameters)
    });

    $(document).on("preview_image",  function (e, parameters) {
        preview_image(parameters)
    });

    $(document).on("append_form",  function (e, parameters) {
        append_form(parameters)
    });

    $(document).on("date-range-picker",  function (e, parameters) {
        date_range_picker(parameters)
    });

    $(document).on("click", ".btn-delete-media", function () {
        delete_media($(this))
    });

    $(document).on('click', '.plus-minus-button', function () {
        plus_minus($(this))
    })
    $(document).on('click', '.btn-open-modal', function () {
        open_modal($(this))
    })
    $(document).on("selectChoice", function (e, parameters) {
        selectChoice(parameters)
    });
};


const App = {
    init,
    translate,
    sweetConfirm,
    sweet,
    ajax,
    serialize,
    serializeObject,
    renderErrors,
    removeDropzone,
    reloadTable,
    loadSwitch,
    initDropzone,
    success,
    debounce,
    clearForm,
    update_or_create,
    sweetInput,
    open_scan_barcode,
    openAddDynamicModal,
    format_price
};

export default App;
