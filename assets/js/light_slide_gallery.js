(function($, Drupal, drupalSettings) {
  Drupal.behaviors.lightSlideGallery = {
    attach(context) {
      $('main', context)
        .once('lightSlideGallery')
        .each(() => {

          const $el = $('[id^=\'imageGallery\']');

          const gallery_style = $el.data('gallery-style');

          console.log('gallery_style', gallery_style);


          switch (gallery_style) {


            case 'slider':
              this.slider($el);
              break;

            case 'grid':
              this.grid($el);
              break;


            case 'animated-grid':
              this.grid($el);
              break;

            default:
              this.grid($el);
              break;
          }


        });
    },

    /**
     *
     * @param $el
     */
    grid($el) {

      // options
      const options = {
        thumbnail: true,
        share: false,
        autoplay: false,
        download: false,
        zoom: false,
      };

      // init
      $el.lightGallery(options);
    },

    /**
     *
     * @param $el
     */
    slider($el) {

      // options
      const options = {
        gallery: true,
        item: 1,
        loop: true,
        thumbItem: 9,
        slideMargin: 0,
        enableDrag: false,
        currentPagerPosition: 'left',
        onSliderLoad(el) {
          el.lightGallery({
            //   selector: '#imageGallery .lslide',
            selector: '[id^=\'imageGallery\'] .lslide',
            share: false,
            autoplay: false,
            download: false,
            zoom: false,
          });
        },
      };

      // init
      $el.lightSlider(options);
    },
  };
})(jQuery, Drupal, drupalSettings);
