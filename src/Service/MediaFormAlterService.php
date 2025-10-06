<?php

declare(strict_types=1);

namespace Drupal\toast_image_editor\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\toast_image_editor\Form\SettingsForm;

/**
 * Service for altering media forms to add image editor.
 */
class MediaFormAlterService {

  use StringTranslationTrait;

  /**
   * Constructs the MediaFormAlterService.
   *
   * @param \Drupal\toast_image_editor\Service\ImageProcessorService $imageProcessor
   *   The image processor service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   */
  public function __construct(
    protected ImageProcessorService $imageProcessor,
    protected ConfigFactoryInterface $configFactory,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Alters the media form to add the image editor.
   *
   * This method integrates the Toast Image Editor into Drupal's media edit
   * form. The save workflow is as follows:
   * 1. User makes edits in the image editor (changes tracked in JavaScript)
   * 2. User clicks "Save" on the media form
   * 3. JavaScript captures edited image as base64 data in hidden field
   * 4. Form submits with image data
   * 5. MediaPresaveService intercepts the data during entity presave
   * 6. Image file is updated and a new media revision is created
   * 7. Form save completes normally.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see \Drupal\toast_image_editor\Service\MediaPresaveService::processMediaPresave()
   */
  public function alterMediaForm(array &$form, FormStateInterface $form_state): void {
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof EntityFormInterface) {
      return;
    }

    $media = $formObject->getEntity();
    if (!$media instanceof MediaInterface) {
      return;
    }

    // Only add an image editor for image media types with existing files.
    if (!$this->imageProcessor->canEditMedia($media)) {
      return;
    }

    // Check permissions.
    if (!$this->currentUser->hasPermission('use toast image editor')) {
      return;
    }

    // Add the image editor section.
    $form['toast_image_editor'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Image Editor'),
      '#weight' => 10,
      '#attributes' => [
        'role' => 'region',
        'aria-label' => $this->t('Image Editor'),
      ],
    ];

    // Loading indicator with accessibility support.
    $form['toast_image_editor']['loading_indicator'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Preparing editor...'),
      '#attributes' => [
        'id' => 'toast-image-editor-loading',
        'class' => ['toast-image-editor-loading'],
        'aria-live' => 'polite',
        'aria-busy' => 'true',
      ],
    ];

    // Status message region for accessibility announcements.
    $form['toast_image_editor']['status_messages'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '',
      '#attributes' => [
        'id' => 'toast-image-editor-status',
        'class' => ['toast-image-editor-status', 'visually-hidden'],
        'aria-live' => 'polite',
        'aria-atomic' => 'true',
        'role' => 'status',
      ],
    ];

    $form['toast_image_editor']['editor_container'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'toast-image-editor-container',
        'data-media-id' => $media->id(),
        'class' => ['toast-image-editor-wrapper'],
      ],
    ];

    $form['toast_image_editor']['editor_placeholder'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => 'toast-image-editor',
        'role' => 'application',
        'aria-label' => $this->t('Image editor'),
        'aria-describedby' => 'toast-image-editor-description',
      ],
    ];

    $form['toast_image_editor']['editor_description'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Select tools from the toolbar below to edit your image. Your changes will save when you submit this form.'),
      '#attributes' => [
        'id' => 'toast-image-editor-description',
        'class' => ['visually-hidden'],
      ],
    ];

    // Add a hidden field to store image editor data.
    $form['toast_image_editor_data'] = [
      '#type' => 'hidden',
      '#value' => '',
    ];

    // Attach the JavaScript library.
    $form['#attached']['library'][] = 'toast_image_editor/toast-image-editor-integration';

    // Pass configuration to JavaScript.
    $config = $this->configFactory->get('toast_image_editor.settings');
    $form['#attached']['drupalSettings']['toastImageEditor'] = [
      'width' => $config->get('editor_width') ? $config->get('editor_width') . 'px' : '100%',
      'height' => $config->get('editor_height') ? $config->get('editor_height') . 'px' : '600px',
      'theme' => $config->get('theme') ?: 'white',
      'mediaId' => $media->id(),
      'saveUrl' => Url::fromRoute('toast_image_editor.media_save', ['media' => $media->id()])->toString(),
      'enabledTools' => $config->get('enabled_tools') ?: SettingsForm::defaultTools(),
      'imageUrl' => $this->imageProcessor->getImageUrl($media),
    ];
  }

}
