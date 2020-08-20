<?php

namespace Drupal\light_slide_gallery\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Render\RendererInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\ImageStyleStorageInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatterBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;

/**
 * Plugin implementation of the 'light_slide_gallery_media_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "light_slide_gallery_media_formatter",
 *   label = @Translation("Lightslide & LightGallery Media Formatter"),
 *   field_types = {
 *     "entity_reference",
 *   }
 * )
 */
class LightSlideGalleryMedia extends ImageFormatter
{
  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The link generator.
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $imageStyleStorage;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs an MediaThumbnailFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\image\ImageStyleStorageInterface $image_style_storage
   *   The image style entity storage handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param LinkGeneratorInterface $link_generator
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    AccountInterface $current_user,
    ImageStyleStorageInterface $image_style_storage,
    RendererInterface $renderer,
    LinkGeneratorInterface $link_generator

  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
      $current_user,
      $image_style_storage
    );
    $this->renderer = $renderer;
    $this->currentUser = $current_user;
    $this->linkGenerator = $link_generator;
    $this->imageStyleStorage = $image_style_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('renderer'),
            $container->get('link_generator')

    );
  }

  /**
   * {@inheritdoc}
   *
   * This has to be overridden because FileFormatterBase expects $item to be
   * of type \Drupal\file\Plugin\Field\FieldType\FileItem and calls
   * isDisplayed() which is not in FieldItemInterface.
   */
  protected function needsEntityLoad(EntityReferenceItem $item)
  {
    return !$item->hasNewEntity();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
      'image_style_default' => 'medium',
      'image_style_thumbnail' => 'unig_thumbnail',
      'image_style_fullscreen' => 'unig_hd',
      'gallery_style' => 'grid',
      'image_link' => ''
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $image_styles = image_style_options(false);

    $gallery_styles = self::gallery_styles_options();

    // Default
    $element['image_style_default'] = [
      '#title' => t('Image style Default'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style_default'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles
    ];

    // Thumbnail
    $element['image_style_thumbnail'] = [
      '#title' => t('Image style Thumbnail'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style_thumbnail'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles
    ];

    // Fullscreen
    $element['image_style_fullscreen'] = [
      '#title' => t('Image style Fullscreen'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style_fullscreen'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
      '#description' => [
        '#markup' => $this->linkGenerator->generate(
          $this->t('Configure Image Styles'),
          new Url('entity.image_style.collection')
        ),
        '#access' => $this->currentUser->hasPermission(
          'administer image styles'
        )
      ]
    ];

    // Gallery Style
    $element['gallery_style'] = [
      '#title' => t('Gallery Style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('gallery_style'),
      '#options' => $gallery_styles
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary()
  {
    $summary = [];
    $image_styles = image_style_options(false);
    $gallery_styles = self::gallery_styles_options();

    // Unset possible 'No defined styles' option.
    unset($image_styles['']);

    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('image_style_default');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = t('Image style: @style', ['@style' => $image_style_setting]);
    } else {
      $summary[] = t('Original image');
    }

    // Gallery Style
    $gallery_style_setting = $this->getSetting('gallery_style');
    if (isset($gallery_style_setting)) {
      $summary[] = t('Gallery style: @style', [
        '@style' => $gallery_styles[$gallery_style_setting]
      ]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode)
  {
    $elements = [];
    $images = [];
    $node = $items->getEntity();
    $nid = $node->id();

    $media_items = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($media_items)) {
      return $elements;
    }

    $image_style_setting = $this->getSetting('image_style');
    // Generate ID for js call
    $field = $items->getName();
    $gallery_style = $this->getSetting('gallery_style');

    $slide_id = 'lightSlideGallery' . '-' . $nid . '-' . $field;
    $slide_id = str_replace(array('_', ' '), '-', $slide_id);
    $cache_contexts = [];

    /** @var \Drupal\media\MediaInterface[] $media_items */
    foreach ($media_items as $delta => $media) {
      // Get ImageStyles from Settings
      $image_style_default = $this->getSetting('image_style_default');
      $image_style_thumbnail = $this->getSetting('image_style_thumbnail');
      $image_style_fullscreen = $this->getSetting('image_style_fullscreen');

      // Load image styles
      $fid = $media->field_media_image->target_id;

      $image_default = self::createImageStyle($fid, $image_style_default);
      $image_thumbnail = self::createImageStyle($fid, $image_style_thumbnail);
      $image_fullscreen = self::createImageStyle($fid, $image_style_fullscreen);

      $images[$delta]['default'] = $image_default;
      $images[$delta]['thumbnail'] = $image_thumbnail;
      $images[$delta]['fullscreen'] = $image_fullscreen;
    }

    $elements[] = [
      '#theme' => 'light_slide_gallery',
      '#images' => $images,

      '#slide_id' => $slide_id,
      '#gallery_style' => $gallery_style
    ];

    $elements['#attached']['library'][] =
      'light_slide_gallery/light_slide_gallery.main';

    return $elements;
  }

  public static function gallery_styles_options()
  {
    return [
      'slider' => 'Slider',
      'grid' => 'Grid',
      'animated_grid' => 'Animated Grid',
      'single_image' => 'Single Image'
    ];
  }

  public static function createImageStyle(
    $img_id_or_file,
    $image_style_id,
    $dont_create = false
  ) {
    $image = [];
    $image_style = ImageStyle::load($image_style_id);

    if ($img_id_or_file && $img_id_or_file instanceof FileInterface) {
      $file = $img_id_or_file;
    } else {
      $file = File::load($img_id_or_file);
    }

    if ($file && $image_style) {
      $file_image = \Drupal::service('image.factory')->get($file->getFileUri());
      /** @var \Drupal\Core\Image\Image $image */

      if ($file_image->isValid()) {
        $image_uri = $file->getFileUri();
        $destination = $image_style->buildUrl($image_uri);

        if (!file_exists($destination)) {
          if (!$dont_create) {
            $image_style->createDerivative($image_uri, $destination);
          }
        }

        $file_size = filesize($image_uri);
        $file_size_formatted = format_size($file_size);
        list($width, $height) = getimagesize($image_uri);

        $image['url'] = $destination;
        $image['uri'] = $image_uri;
        $image['file_size'] = $file_size;
        $image['file_size_formatted'] = $file_size_formatted;
        $image['width'] = $width;
        $image['height'] = $height;
      }
    }
    return $image;
  }
}
