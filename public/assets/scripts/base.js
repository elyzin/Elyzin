/**
 * Base script file to run on every page
 */

var viewPort = 0;
var breakPts = [0, 400, 800, 1200];

$(window).on('load resize', function () {
    // Detect device for responsiveness
    $.each(breakPts, function (i, v) {
        if (v < $(window).width()) viewPort = i;
    });
});

$(function () {
    $('#menu li').has('ul').addClass('parent').removeClass('open').find('ul').hide();

    $('#menu a[href=\'#\']').on('click', function () {
        var targetUl = $($(this)).next('ul');
        if ($('#menu').css('position') == 'fixed' && $(this).parent('li').hasClass('parent')) {
            $('#menu>ul ul').not(targetUl).not($(targetUl).parents('ul')).slideUp('fast').parent('li').removeClass('open');
            $(targetUl).slideToggle('fast').parent('li').toggleClass('open');
            return false;
        }
    });
    $('#hamburger').on('click', function () {
        $('#menu, .hamburger-menu').toggleClass('open');
    });
});
$(document).on("click", function (e) {
    if ($('#menu').hasClass('open') && $(e.target).closest("#menu, #hamburger").length == 0) {
        $('#menu, .hamburger-menu').toggleClass('open');
    }
});


// Plugin autoloader
// Compressed Version
//$(function(){$("[data-plugin]").each(function(){var n=$(this),t=$.trim(n.attr("data-plugin"));if($()[t]){var c={};null!=conf&&$.each(["id","data-conf"],function(t,a){null!=n.attr(a)&&"object"===$.type(conf[n.attr(a)])&&(c=conf[n.attr(a)])}),n[t](c)}})});
// Full Version

$(function () {
    $("[data-plugin]").each(function () {
        var elem = $(this);
        var plug = $.trim(elem.attr("data-plugin"));
        if ($()[plug]) {
            var confObj = {};
            if (typeof conf === 'undefined') conf = {};
            $.each(['id', 'data-conf'], function (i, val) {
                if (elem.attr(val) != null && $.type(conf[elem.attr(val)]) === 'object') { confObj = conf[elem.attr(val)]; }
            });
            elem[plug](confObj);
        } else {
            console.log('Plugin \'' + plug + '\' not loaded.');
        }
    });
});