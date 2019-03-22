(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.lightSlideGallery = {
    attach(context) {
      $('main', context)
        .once('lightSlideGallery')
        .each(() => {
          $("[id^='imageGallery']").lightSlider({
            gallery: true,
            item: 1,
            loop: true,
            thumbItem: 9,
            slideMargin: 0,
            enableDrag: false,
            currentPagerPosition: 'left',
            onSliderLoad: function (el) {
              el.lightGallery({
             //   selector: '#imageGallery .lslide',
                selector:  "[id^='imageGallery'] .lslide",
              });
            },
          });
        });
    },
  };
})(jQuery, Drupal, drupalSettings);
