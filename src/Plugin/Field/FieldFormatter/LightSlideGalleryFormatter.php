<?php

namespace Drupal\light_slide_gallery\Plugin\Field\FieldFormatter;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\image\Entity\ImageStyle;
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
 * Plugin implementation of the 'light_slide_gallery_image_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "light_slide_gallery_image_formatter",
 *   label = @Translation("Lightslide & LightGallery Formatter"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class LightSlideGalleryFormatter extends ImageFormatterBase implements
  ContainerFactoryPluginInterface
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
   * Constructs a new LightSlideGalleryFormatter object.
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
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generator service.
   * @param \Drupal\Core\Entity\EntityStorageInterface $image_style_storage
   *   The entity storage for the image.
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
    LinkGeneratorInterface $link_generator,
    EntityStorageInterface $image_style_storage
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings
    );
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
      $container->get('link_generator'),
      $container->get('entity_type.manager')->getStorage('image_style')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
      'image_style_default' => '',
      'image_style_thumbnail' => '',
      'image_style_fullscreen' => '',
      'gallery_style' => 0,
      'image_link' => '',
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
      '#options' => $image_styles,
    ];

    // Tumbnail
    $element['image_style_thumbnail'] = [
      '#title' => t('Image style Thumbnail'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_style_thumbnail'),
      '#empty_option' => t('None (original image)'),
      '#options' => $image_styles,
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
        ),
      ],
    ];

    // Gallery Style
    $element['gallery_style'] = [
      '#title' => t('Gallery Style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('gallery_style'),
      '#options' => $gallery_styles,
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
        '@style' => $gallery_styles[$gallery_style_setting],
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
    $url = null;
    $files = $this->getEntitiesToView($items, $langcode);
    $node = $items->getEntity();
    $nid = $node->id();

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    // Generate ID for js call
    $field = $items->getName();
    $gallery_style = $this->getSetting('gallery_style');

    $slide_id = 'lightSlideGallery' . '-' . $nid . '-' . $field;
    $slide_id = str_replace(array('_', ' '), '-', $slide_id);

    // Image Style
    $image_style_default = $this->getSetting('image_style_default');
    $image_style_thumbnail = $this->getSetting('image_style_thumbnail');
    $image_style_fullscreen = $this->getSetting('image_style_fullscreen');

    // Loop over all images in field
    foreach ($files as $delta => $file) {
      $image_default = self::createImageStyle($file, $image_style_default);
      $image_thumbnail = self::createImageStyle($file, $image_style_thumbnail);
      $image_fullscreen = self::createImageStyle(
        $file,
        $image_style_fullscreen
      );

      $images[$delta]['default'] = $image_default;
      $images[$delta]['thumbnail'] = $image_thumbnail;
      $images[$delta]['fullscreen'] = $image_fullscreen;
    }

    // Extract field item attributes for the theme function, and unset them
    // from the $item so that the field template does not re-render them.
    $item = $file->_referringItem;
    $item_attributes = $item->_attributes;
    unset($item->_attributes);

    $elements = [
      '#theme' => 'light_slide_gallery',
      '#item' => $item,
      '#item_attributes' => $item_attributes,
      '#images' => $images,
      '#slide_id' => $slide_id,
      '#gallery_style' => $gallery_style,
    ];

    // Not to cache this field formatter.
    $elements['#cache']['max-age'] = 0;

    $elements['#attached']['library'][] =
      'light_slide_gallery/light_slide_gallery.main';

    return $elements;
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

  public static function gallery_styles_options()
  {
    return [
      'slider' => 'Slider',
      'test' => 'Grid',
      'animated_grid' => 'Animated Grid',
      'single_image' => 'Single Image',
    ];
  }
}
