/* =========================================================================
   SRD Admin — theme.js
   Requires jQuery (already loaded on the existing pages).
   Handles: mobile off-canvas sidebar toggle + backdrop close.
   ========================================================================= */
$(function () {
  var $body = $('body');

  function openSidebar() {
    $body.addClass('srd-sidebar-open');
  }
  function closeSidebar() {
    $body.removeClass('srd-sidebar-open');
  }

  $(document).on('click', '.js-srd-menu-toggle', function (e) {
    e.preventDefault();
    $body.hasClass('srd-sidebar-open') ? closeSidebar() : openSidebar();
  });

  $(document).on('click', '.srd-sidebar-backdrop, .js-srd-sidebar-close', function () {
    closeSidebar();
  });

  // close the drawer automatically if the viewport is resized back to desktop
  $(window).on('resize', function () {
    if ($(window).width() >= 992) closeSidebar();
  });

  // mark the current nav item active based on the current path
  var path = window.location.pathname.replace(/\/$/, '');
  $('.srd-nav-item[href]').each(function () {
    var href = $(this).attr('href').replace(/\/$/, '');
    if (href && path.indexOf(href) === 0) {
      $('.srd-nav-item').removeClass('active');
      $(this).addClass('active');
    }
  });
});
