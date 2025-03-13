import $ from 'jquery';
import '@firstandthird/toc/dist/toc.js';

(function () {
    $(document).on('click', '[data-toggle="lightbox"]', function (event) {
        event.preventDefault();
        $(this).ekkoLightbox();
    });

    $('.thumbnail.hidden img').each((_, image) => {
        if (image.complete) {
            $(image).closest('.thumbnail').fadeIn(500).removeClass('hidden');
        } else {
            $(image).on('load', () => {
                $(image).closest('.thumbnail').fadeIn(500).removeClass('hidden');
            });
        }
    });

    /**
     * This part handles the highlighting functionality.
     * We use the scroll functionality again, some array creation and
     * manipulation, class adding and class removing, and conditional testing
     */
    const aChildren = $('nav[data-lockfixed="true"] li').children(); // find the a children of the list items
    const aArray = []; // create the empty aArray
    for (let i = 0; i < aChildren.length; i++) {
        const aChild = aChildren[i];
        const ahref = $(aChild).attr('href');
        aArray.push(ahref);
    } // this for loop fills the aArray with attribute href values

    if ($('nav[data-lockfixed="true"]').length) {
        stickNav();
        $(window).scroll(() => {
            stickNav();
        });
        $(window).resize(() => {
            stickNav();
        });
    }

    function stickNav() {
        const window_top = $(window).scrollTop(); // the "12" should equal the margin-top value for nav.stick
        const div_top = $('#nav-anchor').offset().top - 30;
        const $nav = $('nav[data-lockfixed="true"]');

        if (window_top > div_top) {
            $nav.addClass('stick');
            if ($nav.width() !== $('#nav-anchor').width() - 40) {
                $nav.css({ width: $('#nav-anchor').width() });
            }
        } else {
            $nav.removeClass('stick');
        }

        const windowPos = $(window).scrollTop(); // get the offset of the window from the top of page
        const windowHeight = $(window).height(); // get the height of the window
        const docHeight = $(document).height();

        for (let i = 0; i < aArray.length; i++) {
            var footerHeight = $('.page-footer').outerHeight();
            const navHeight = $nav.outerHeight();
            var navToBottom = windowHeight - navHeight;
        }

        if (windowPos + windowHeight >= docHeight - footerHeight) {
            const footerInSight = (windowPos + windowHeight) - (docHeight - footerHeight);
            if (footerInSight > (navToBottom - 18)) {
                $nav.css({ top: (navToBottom - footerInSight - 18) });
            } else {
                $nav.css({ top: 30 });
            }
        }
    }

    jQuery.fn.load = function (callback) { $(window).on('load', callback); };

    // Use special font-family for greek characters
    $('article').markRegExp(
        /(?:[[.,(|+][[\].,():|+\- ]*)?[\u0370-\u03ff\u1f00-\u1fff]+(?:[[\].,():|+\- ]*[\u0370-\u03ff\u1f00-\u1fff]+)*(?:[[\].,():|+\- ]*[\].,):|])?/g,
        {
            element: 'span',
            className: 'greek',
            exclude: [
                '.greek',
                '.greek *',
            ],
        },
    );

    // Make long lists collapsible
    $('.collapse-toggle[data-action="display"]').click(function () {
        $(this).closest('.collapsed').removeClass('collapsed').addClass('collapsible');
        return false;
    });
    $('.collapse-toggle[data-action="hide"]').click(function () {
        $(this).closest('.collapsible').removeClass('collapsible').addClass('collapsed');
        return false;
    });
}());
