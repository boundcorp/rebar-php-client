function dialogue(content, title) {
    var fullContent = $("<div/>");
    fullContent.addClass("white-popup");
    fullContent.append($("<div/>").addClass("ic-modal-title").text(title));
    var cont = $("<div/>").addClass("ic-modal-content");
    for (var i = 0; i < content.length; i++) {// in content) {
        cont.append(content.get(i));
    }
    cont.find(":button").addClass("mfp-close").addClass("mfp-close-ok");

    fullContent.append(cont);

    $.magnificPopup.open({
        //modal: true,
        closeOnContentClick: false,
        items: {
            //closeBtnInside: true,
            src: fullContent,
            type: 'inline'
        }
    }, 0);
}
// Our Alert method
function Alert(message)
{
    // Content will consist of the message and an ok button
    var message = $('<p />', {html: message}),
            ok = $('<button />', {text: 'Ok', 'class': 'full'});

    dialogue(message.add(ok), 'Alert!');
}

// Our Prompt method
function Prompt(question, initial, callback)
{
    // Content will consist of a question elem and input, with ok/cancel buttons
    var message = $('<p />', {text: question}),
            input = $('<input />', {val: initial}),
            ok = $('<button />', {
                text: 'Ok',
                click: function () {
                    callback(input.val());
                }
            }),
            cancel = $('<button />', {
                text: 'Cancel',
                click: function () {
                    callback(null);
                }
            });

    dialogue(message.add(input).add(ok).add(cancel), 'Attention!');
}

// Our Confirm method
function Confirm(question, callback)
{
    // Content will consist of the question and ok/cancel buttons
    var message = $('<p />', {text: question}),
            ok = $('<button />', {
                text: 'Ok',
                click: function () {
                    callback(true);
                }
            }),
            cancel = $('<button />', {
                text: 'Cancel',
                click: function () {
                    callback(false);
                }
            });

    dialogue(message.add(ok).add(cancel), 'Do you agree?');
}

// Our Question method
function Question(question, callback)
{
    // Content will consist of the question and ok/cancel buttons
    var message = $('<p />', {html: question}),
            ok = $('<button />', {
                text: 'Yes',
                click: function () {
                    callback(true);
                }
            }),
            cancel = $('<button />', {
                text: 'No',
                click: function () {
                    callback(false);
                }
            });

    dialogue(message.add(ok).add(cancel), 'Please Review');
}

function modalOnClick() {
    $('.modalClick').each(function () {
        var url = $(this).attr('href');
        $(this).magnificPopup({
            closeOnContentClick: false,
            type: 'ajax',
            ajax: {
                settings: null,
                cursor: 'mfp-ajax-cur', // CSS class that will be added to body during the loading (adds "progress" cursor)
                tError: '<a href="%url%">The content</a> could not be loaded.' //  Error message, can contain %curr% and %total% tags if gallery is enabled
            },
            callbacks: {
                parseAjax: function (mfpResponse) {
                    console.log(mfpResponse.data);
                    mfpResponse.data = $(mfpResponse.data).closest('.content');
                },
                ajaxContentAdded: function () {
                    modalOnClick();
                }
            }
        });

    }).click(function (event) {
        event.preventDefault();
    });
}

function processBankruptcyDebtLead(request) {
    request['m'] = 'processBankruptcyDebtLead';

    // Before sending, the button that clicked should turn into a spinner.
    $.ajax({
        type: "POST",
        url: "../api/",
        data: request,
        dataType: "json",
        success: function (data) {
            if (data.complete) {
                $('input[name="response"]').val('true');
                $('form').submit();
            } else {
                $('.ic-loading').hide();
                $('.step.show').show();
                var errors = 'The following fields contain errors: <br /><br /><ul>';
                $.each(data.errors, function (k, v) {
                    errors = errors + '<li>' + v + '</li>';
                });
                errors = errors + '</ul><br />Please correct these errors and try again.';
                Alert(errors);
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            console.log("Status: " + textStatus);
            console.log("Error: " + errorThrown);
        }
    });
}

function processStudentDebtLead(request) {
    request['m'] = 'processStudentDebtLead';

    // Before sending, the button that clicked should turn into a spinner.
    $.ajax({
        type: "POST",
        url: "../api/",
        data: request,
        dataType: "json",
        success: function (data) {
            if (data.complete) {
                $('input[name="response"]').val('true');
                $('form').submit();
            } else {
                $('.ic-loading').hide();
                $('.step.show').show();
                var errors = 'The following fields contain errors: <br /><br /><ul>';
                $.each(data.errors, function (k, v) {
                    errors = errors + '<li>' + v + '</li>';
                });
                errors = errors + '</ul><br />Please correct these errors and try again.';
                Alert(errors);
            }
        },
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            console.log("Status: " + textStatus);
            console.log("Error: " + errorThrown);
        }
    });
}

function nextSlide() {
    $('.step').each(function (i, v) {
        if ($(this).hasClass('show')) {
            $(this).fadeOut('fast', function () {
                $(this).removeClass('show');
                var next = $(this).next();
                $('.ic-loading').hide();
                next.fadeIn('fast', function () {
                    next.addClass('show');
                });
            });
            return;
        }
    });
}

function showLoading() {
    window.internalLink = true;
    window.formSubmitted = true;
    $.magnificPopup.open({
        closeOnContentClick: false,
        closeOnBgClick: false,
        showCloseBtn: false,
        enableEscapeKey: false,
        items: {
            src: '#ic-loading', // can be a HTML string, jQuery object, or CSS selector
            type: 'inline'
        }
    });
}

function evalScripts(el) {
    var scripts = el.filter('script');
    var i = 0;
    var end = scripts.length;

    for (i; i < end; i++) {
        eval(scripts[i].innerHTML);
    }

    scripts = null;
}