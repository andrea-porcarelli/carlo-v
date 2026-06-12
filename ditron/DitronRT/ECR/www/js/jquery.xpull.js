/*
 Xpull - pull to refresh jQuery plugin for iOS and Android
 by Slobodan Jovanovic
 Initially made for Spreya app spreya.com

 Usage:

 $('selector').xpull(options);

 Example

 $('#container').xpull({
    'callback':function(){
        console.log('Released...');
    }
 });

 Options:

 {
    'pullThreshold':50, // Pull threshold - amount in pixels after which the callback is activated
    'callback':function(){}, // triggers after user pulls the content over pull threshold
    'spinnerTimeout':2000, // timeout in miliseconds after which the loading indicator stops spinning. If set to 0 - the loading will be indefinite
    'loadingHtml': '<div class="pull-indicator my-custom-pull-indicator"><div class="arrow-body my-custom-arrow-class"></div><div class="triangle-down"></div><div class="pull-spinner">Hold tight, reloading!</div></div>' // optional - customize the default loading markup (certain classes need to be present for it to function though: .pull-indicator, .arrow-body, .triangle-down, .pull-spinner)
 }

 To get the instance of Xpull:

 var xpull = $('selector').data("plugin_xpull");

  Methods:

  reset() - cancels he spinning and resets the plugin to initial state. Example: $('#container').data('plugin_xpull').reset();
  
*/

(function ($, window, document, undefined) {
        var text_pull = localizeSingleWord("common", "pull");
        var text_release = localizeSingleWord("common", "release");
        var text_refresh = localizeSingleWord("common", "refresh");

        var indicatorH = '';
        indicatorH += '<div class="pull-indicator">										'
        indicatorH += '	<table>															'
        indicatorH += '		<tr>														'
        indicatorH += '			<td>													'
        indicatorH += '				<div class="arrow">	'
        indicatorH += '					<div class="arrow-body"></div>					'
        indicatorH += '					<div class="triangle-down"></div>				'
        indicatorH += '				</div>												'
        indicatorH += '				<div class="pull-spinner"></div>					'
        indicatorH += '			</td>													'
        indicatorH += '			<td>													'
        indicatorH += '				<div class="pull-text">' + text_pull + '</div>	'
        indicatorH += '			</td>													'
        indicatorH += '		</tr>														'
        indicatorH += '	</table>														'
        indicatorH += '</div>															'

        var pluginName = "xpull",
            defaults = {
                pullThreshold: 100,
                maxPullThreshold: 100,
                spinnerTimeout: 2000,
                scrollingDom: null,  // if null, specified element
                onPullStart: function () { },
                onPullEnd: function () { },
                callback: function () { },
                //            loadingHtml: '<div class="pull-indicator"><div class="arrow" style="border:1px solid red;"><div class="arrow-body"></div><div class="triangle-down"></div></div><div class="pull-spinner"></div><div class="pull-text">text</div></div>'
                loadingHtml: indicatorH
            };
        function Plugin(element, options) {
            this.element = element;
            this.options = $.extend({}, defaults, options);
            this._defaults = defaults;
            this._name = pluginName;
            this.init();
        }
        Plugin.prototype = {
            init: function () {
                var inst = this;
                var elm = $(inst.element).children();
                inst.elm = elm;
                elm.parent().find('.pull-indicator').remove();
                elm.parent().prepend(inst.options.loadingHtml);
                inst.indicator = elm.parent().find('.pull-indicator:eq(0)');
                inst.spinner = elm.parent().find('.pull-spinner:eq(0)');
                //inst.arrow = elm.parent().find('.arrow-body:eq(0),.triangle-down:eq(0)');
                inst.arrow = elm.parent().find('.arrow:eq(0)');
                inst.text = elm.parent().find('.pull-text:eq(0)');
                inst.indicatorHeight = inst.indicator.outerHeight();
                $(elm).css({
                    '-webkit-transform': "translate3d(0px, -" + inst.indicatorHeight + "px, 0px)"
                });
                elm.parent().css({
                    '-webkit-overflow-scrolling': 'touch'
                });
                var ofstop = elm.parent().offset().top;
                var fingerOffset = 0;
                var top = 0;
                var hasc = false;
                inst.elast = true;
                inst.arrow.css('visibility', 'hidden');
                inst.indicatorHidden = true;
                elm.unbind('touchstart.' + pluginName);
                elm.on('touchstart.' + pluginName, function (ev) {
                    inst.options.onPullStart.call(this);
                    fingerOffset = ev.originalEvent.touches[0].pageY - ofstop
                });
                elm.unbind('touchmove.' + pluginName);
                elm.on('touchmove.' + pluginName, function (ev) {
                    if (elm.position().top < 0 || (inst.options.scrollingDom || elm.parent()).scrollTop() > 0 || document.body.scrollTop > 0) { // trigger callback only if pulled from the top of the list
                        return true;
                    }
                    if (inst.indicatorHidden) {
                        inst.arrow.css('visibility', 'visible');
                        inst.indicatorHidden = false;
                    }
                    top = (ev.originalEvent.touches[0].pageY - ofstop - fingerOffset);
                    if (top > 1) {

                        if (inst.elast) {
                            $(document.body).on('touchmove.' + pluginName, function (e) {
                                e.preventDefault();
                            });
                            inst.elast = false;
                        }

                        if (top <= (parseInt(inst.options.pullThreshold) + inst.options.maxPullThreshold)) {

                            $(elm).css({
                                '-webkit-transform': "translate3d(0px, " + (top - inst.indicatorHeight) + "px, 0px)"
                            });

                            inst.indicator.css({
                                'top': (top - inst.indicatorHeight) + "px"
                            });
                        }

                        if (top > inst.options.pullThreshold && !hasc) {
                            //inst.indicator.addClass('arrow-rotate-180');
                            inst.arrow.addClass('arrow-rotate-180');
                            inst.arrow.removeClass('arrow-rotate-0');
                            inst.text.text(text_release);
                        } else if (top <= inst.options.pullThreshold && hasc) {
                            //inst.indicator.removeClass('arrow-rotate-180');
                            inst.arrow.removeClass('arrow-rotate-180');
                            inst.arrow.addClass('arrow-rotate-0');
                            inst.text.text(text_pull);
                        }
                    } else {
                        $(document.body).unbind('touchmove.' + pluginName);
                        inst.elast = true;
                    }
                    hasc = inst.arrow.hasClass('arrow-rotate-180');
                    //hasc = inst.indicator.hasClass('arrow-rotate-180');

                });
                elm.unbind('touchend.' + pluginName);
                elm.on('touchend.' + pluginName, function (ev) {
                    inst.options.onPullEnd.call(this);
                    if (top > 0) {
                        if (top > inst.options.pullThreshold) {
                            inst.options.callback.call(this);
                            inst.arrow.hide();
                            inst.spinner.show();
                            inst.text.text(text_refresh);
                            elm.css({
                                '-webkit-transform': 'translate3d(0px, 0px, 0px)',
                                '-webkit-transition': '-webkit-transform 300ms ease'
                            });
                            inst.indicator.css({
                                'top': "0px",
                                '-webkit-transition': 'top 300ms ease'
                            });
                            if (inst.options.spinnerTimeout) {
                                setTimeout(function () {
                                    inst.reset();
                                }, inst.options.spinnerTimeout);
                            }

                        } else {
                            inst.indicator.css({
                                'top': "-" + inst.indicatorHeight + "px",
                                '-webkit-transition': 'top 300ms ease'
                            });
                            elm.css({
                                '-webkit-transform': 'translate3d(0px, -' + inst.indicatorHeight + 'px, 0px)',
                                '-webkit-transition': '-webkit-transform 300ms ease'
                            });
                        }
                        top = 0;
                    }
                    if (!inst.indicatorHidden) {
                        inst.arrow.css('visibility', 'hidden');
                        inst.indicatorHidden = true;
                    }
                    setTimeout(function () {
                        //inst.indicator.removeClass('arrow-rotate-180');
                        elm.css({
                            '-webkit-transition': ''
                        });
                        inst.indicator.css({
                            '-webkit-transition': ''
                        });
                        $(document.body).unbind('touchmove.' + pluginName);
                        inst.elast = true;
                    }, 300);
                });
            },
            reset: function () {
                var inst = this;
                var elm = inst.elm;
                inst.indicator.css({
                    'top': "-" + inst.indicatorHeight + "px",
                    '-webkit-transition': 'top 300ms ease'
                });
                elm.css({
                    '-webkit-transform': 'translate3d(0px, -' + inst.indicatorHeight + 'px, 0px)',
                    '-webkit-transition': '-webkit-transform 300ms ease'
                });
                setTimeout(function () {
                    inst.arrow.show();
                    inst.spinner.hide();
                    inst.text.text(text_pull);
                    inst.indicator.removeClass('arrow-rotate-180');
                    elm.css({
                        '-webkit-transition': ''
                    });
                    inst.indicator.css({
                        '-webkit-transition': ''
                    });
                    $(document.body).unbind('touchmove.' + pluginName);
                    inst.elast = true;
                }, 300);
            },
            insertCss: function (css, id) {
                var el = document.getElementById(id);
                if (el) {
                    el.parentNode.removeChild(el);
                }
                var csse = document.createElement('style');
                csse.type = 'text/css';
                csse.id = id;
                if (csse.styleSheet) {
                    document.getElementsByTagName("head")[0].appendChild(csse);
                    csse.styleSheet.cssText = css;
                }
                else {
                    var rules = document.createTextNode(css);
                    csse.appendChild(rules);
                    document.getElementsByTagName("head")[0].appendChild(csse);
                }
            }
        };
        $.fn[pluginName] = function (options) {
            return this.each(function () {
                if (!$.data(this, "plugin_" + pluginName)) {
                    $.data(this, "plugin_" + pluginName, new Plugin(this, options));
                }
            });
        };

    })(jQuery, window, document);
