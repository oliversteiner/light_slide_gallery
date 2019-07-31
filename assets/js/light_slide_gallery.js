(function($, Drupal, drupalSettings) {
  Drupal.behaviors.lightSlideGallery = {
    attach(context) {
      $('main', context)
        .once('lightSlideGallery')
        .each(() => {

          const $elems = $('[id^=\'lightSlideGallery\']');

          const scope = this;

          $elems.each(function() {

            const $elem = $(this);
            const galleryStyle = $elem.data('gallery-style');


            switch (galleryStyle) {

              case 'slider':
                scope.slider(this);
                break;

              case 'grid':
                scope.grid(this);
                break;

              case 'single-image':
                scope.singleImage(this);
                break;

              case 'animated-grid':
                scope.grid(this);
                break;

              default:
                scope.grid(this);
                break;
            }
          });


        });
    },
    /**
     *
     * @param $el
     */
    singleImage(elem) {
      const $elem = $(elem);

      // options
      const options = {
        thumbnail: false,
        share: false,
        autoplay: false,
        download: false,
        zoom: false,
        loop: false,
        controls: false,
        counter: false,

      };

      // init
      $elem.lightGallery(options);
    },

    /**
     *
     * @param $el
     */
    grid(elem) {
      const $elem = $(elem);

      // options
      const options = {
        thumbnail: true,
        share: false,
        autoplay: false,
        download: false,
        zoom: false,
      };

      // init
      $elem.lightGallery(options);
    },

    /**
     *
     * @param $el
     */
    slider(elem) {
      const $elem = $(elem);

      // options
      const options = {
        gallery: true,
        item: 1,
        loop: true,
        thumbItem: 9,
        slideMargin: 0,
        enableDrag: false,
        currentPagerPosition: 'left',
        onSliderLoad($elem) {
          $elem.lightGallery({
            selector: '[id^=\'lightSlideGallery\'] .lslide',
            share: false,
            autoplay: false,
            download: false,
            zoom: false,
          });
        },
      };

      // init
      $elem.lightSlider(options);
    },
  };
})(jQuery, Drupal);
