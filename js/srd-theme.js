/* =========================================================================
   SRD Admin — srd-theme.js
   Requires jQuery (already loaded on the existing pages).
   Handles: mobile off-canvas sidebar toggle + backdrop close.
   Active-nav-item is set server-side in partials/sidebar.blade.php
   (Route::current()->uri()), so the client-side detection from the
   original theme.js isn't needed here.
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
});
