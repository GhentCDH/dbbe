import $ from 'jquery';
require('mark.js/dist/jquery.mark.js');
require('bootstrap-sass');
require('ekko-lightbox');
import '@firstandthird/toc/dist/toc.js';

(function() {
    $(document).on('click', '[data-toggle="lightbox"]', function (event) {
        event.preventDefault();
        $(this).ekkoLightbox();
    });

    $('.thumbnail.hidden img').on('load', function (event) {
        $(this).closest('.thumbnail').fadeIn(500).removeClass('hidden');
    });

    /**
     * This part handles the highlighting functionality.
     * We use the scroll functionality again, some array creation and
     * manipulation, class adding and class removing, and conditional testing
     */
    var aChildren = $('nav[data-lockfixed="true"] li').children(); // find the a children of the list items
    var aArray = []; // create the empty aArray
    for (var i=0; i < aChildren.length; i++) {
        var aChild = aChildren[i];
        var ahref = $(aChild).attr('href');
        aArray.push(ahref);
    } // this for loop fills the aArray with attribute href values

    if($('nav[data-lockfixed="true"]').length) {
        stickNav();
        $(window).scroll(function(){
            stickNav();
        });
        $(window).resize(function(){
            stickNav();
        });
    }

    function stickNav () {
        var window_top = $(window).scrollTop(); // the "12" should equal the margin-top value for nav.stick
        var div_top = $('#nav-anchor').offset().top - 30;
        var $nav = $('nav[data-lockfixed="true"]');

        if (window_top > div_top) {
            $nav.addClass('stick');
            if($nav.width() !== $('#nav-anchor').width() - 40) {
                $nav.css({'width':$('#nav-anchor').width()});
            }
        } else {
            $nav.removeClass('stick');
        }

        var windowPos = $(window).scrollTop(); // get the offset of the window from the top of page
        var windowHeight = $(window).height(); // get the height of the window
        var docHeight = $(document).height();

        for (var i=0; i < aArray.length; i++) {
            var footerHeight = $('.page-footer').outerHeight();
            var navHeight = $nav.outerHeight();
            var navToBottom = windowHeight - navHeight;
        }

        if(windowPos + windowHeight >= docHeight - footerHeight) {
            var footerInSight =  (windowPos + windowHeight) - (docHeight - footerHeight);
            if(footerInSight > (navToBottom - 18)) {
                $nav.css({'top': (navToBottom - footerInSight - 18)})
            }else {
                $nav.css({'top': 30})
            }
        }
    }

    jQuery.fn.load = function(callback){ $(window).on("load", callback) };

    // Use special font-family for greek characters
    $('article').markRegExp(
        /(?:[[.,(|+][[\].,():|+\- ]*)?[\u0370-\u03ff\u1f00-\u1fff]+(?:[[\].,():|+\- ]*[\u0370-\u03ff\u1f00-\u1fff]+)*(?:[[\].,():|+\- ]*[\].,):|])?/g,
        {
            'element': 'span',
            'className': 'greek',
            'exclude': [
                '.greek',
                '.greek *'
            ]
        }
    );

    // Make long lists collapsible
    $('.collapse-toggle[data-action="display"]').click(function(){
        $(this).closest('.collapsed').removeClass('collapsed').addClass('collapsible');
        return false;
    });
    $('.collapse-toggle[data-action="hide"]').click(function(){
        $(this).closest('.collapsible').removeClass('collapsible').addClass('collapsed');
        return false;
    });
}());
