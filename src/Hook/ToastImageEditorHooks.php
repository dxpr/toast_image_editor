<?php

namespace Drupal\toast_image_editor\Hook;

use Drupal\media\MediaInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\toast_image_editor\Service\MediaFormAlterService;
use Drupal\toast_image_editor\Service\MediaPresaveService;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for toast_image_editor.
 */
class ToastImageEditorHooks {
  use StringTranslationTrait;

  /**
   * Constructs the hook service.
   */
  public function __construct(
    protected MediaFormAlterService $toastImageEditorMediaFormAlter,
    protected MediaPresaveService $toastImageEditorMediaPresave,
    protected MessengerInterface $messenger,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): string {
    switch ($route_name) {
      case 'help.page.toast_image_editor':
        $output = '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('Edit images directly in Drupal. This module lets you crop, rotate, add text, and apply filters right from the media edit screen. All changes create new revisions, so your original images stay safe.') . '</p>';
        $output .= '<h2>' . $this->t('What you can do') . '</h2>';
        $output .= '<ul>';
        $output .= '<li>' . $this->t('Crop, rotate, flip, and resize images') . '</li>';
        $output .= '<li>' . $this->t('Draw, add shapes, and insert text') . '</li>';
        $output .= '<li>' . $this->t('Apply filters and color effects') . '</li>';
        $output .= '<li>' . $this->t('Track changes with automatic media revisions') . '</li>';
        $output .= '<li>' . $this->t('Work offline with no external dependencies') . '</li>';
        $output .= '</ul>';
        $output .= '<h2>' . $this->t('Quick start') . '</h2>';
        $output .= '<ol>';
        $output .= '<li>' . $this->t('Go to <a href=":media">Content > Media</a>', [
          ':media' => Url::fromRoute('entity.media.collection')->toString(),
        ]) . '</li>';
        $output .= '<li>' . $this->t('Click Edit on any image') . '</li>';
        $output .= '<li>' . $this->t('Find the Image Editor section') . '</li>';
        $output .= '<li>' . $this->t('Make your changes') . '</li>';
        $output .= '<li>' . $this->t('Click Save to create a new revision') . '</li>';
        $output .= '</ol>';
        $output .= '<h2>' . $this->t('Settings') . '</h2>';
        $output .= '<p>' . $this->t('Visit <a href=":config">the settings page</a> to:', [
          ':config' => Url::fromRoute('toast_image_editor.settings')->toString(),
        ]) . '</p>';
        $output .= '<ul>';
        $output .= '<li>' . $this->t('Show or hide specific tools') . '</li>';
        $output .= '<li>' . $this->t('Switch between light and dark themes') . '</li>';
        $output .= '<li>' . $this->t('Set editor size') . '</li>';
        $output .= '<li>' . $this->t('Control maximum file size') . '</li>';
        $output .= '</ul>';
        $output .= '<h2>' . $this->t('Permissions') . '</h2>';
        $output .= '<p>' . $this->t('Control who can edit images and change settings at <a href=":permissions">People > Permissions</a>:', [
          ':permissions' => Url::fromRoute('user.admin_permissions', [], [
            'fragment' => 'module-toast_image_editor',
          ])->toString(),
        ]) . '</p>';
        $output .= '<ul>';
        $output .= '<li>' . $this->t('<strong>Use toast image editor</strong>: allows editing images') . '</li>';
        $output .= '<li>' . $this->t('<strong>Administer Toast Image Editor</strong>: allows changing module settings') . '</li>';
        $output .= '</ul>';
        return $output;

      case 'toast_image_editor.settings':
        return '<p>' . $this->t('Choose which tools appear in the editor and customize how it looks.') . '</p>';
    }
    return '';
  }

  /**
   * Implements hook_form_FORM_ID_alter() for "form_media_form".
   */
  #[Hook('form_media_form_alter')]
  public function formMediaFormAlter(array &$form, FormStateInterface $form_state): void {
    try {
      /** @var \Drupal\toast_image_editor\Service\MediaFormAlterService $formAlterService */
      $formAlterService = $this->toastImageEditorMediaFormAlter;
      $formAlterService->alterMediaForm($form, $form_state);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('toast_image_editor')->error('Form alter failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('Toast Image Editor form error: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for "media".
   */
  #[Hook('media_presave')]
  public function mediaPresave(MediaInterface $entity): void {
    try {
      /** @var \Drupal\toast_image_editor\Service\MediaPresaveService $presaveService */
      $presaveService = $this->toastImageEditorMediaPresave;
      $presaveService->processMediaPresave($entity);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('toast_image_editor')->error('Failed to get presave service: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('Toast Image Editor error: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

}
