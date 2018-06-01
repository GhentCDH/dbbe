var $ = require('jquery')
require('bootstrap-sass')
require('ekko-lightbox')

$(document).on('click', '[data-toggle="lightbox"]', function(event) {
    event.preventDefault();
    $(this).ekkoLightbox();
});

$('.thumbnail.hidden img').on('load', function(event) {
    $(this).closest('.thumbnail').fadeIn(500).removeClass('hidden');
});
