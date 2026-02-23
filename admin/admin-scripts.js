jQuery(document).ready(function($){

    // Predefined icons accordion
    $('.lumn-utilites-admin-accordion-header').click(function() {
        $(this).parent().toggleClass('active');
    });
});