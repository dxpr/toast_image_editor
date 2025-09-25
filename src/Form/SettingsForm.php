<?php

declare(strict_types=1);

namespace Drupal\toast_image_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Toast Image Editor settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'toast_image_editor_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['toast_image_editor.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('toast_image_editor.settings');

    $form['tools'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Available Tools'),
      '#description' => $this->t('Select which tools should be available in the Toast Image Editor.'),
    ];

    $tools = $this->getAvailableTools();
    $enabledTools = $config->get('enabled_tools') ?: array_keys($tools);

    foreach ($tools as $key => $tool) {
      $form['tools']['enabled_tools'][$key] = [
        '#type' => 'checkbox',
        '#title' => $tool['title'],
        '#description' => $tool['description'],
        '#default_value' => in_array($key, $enabledTools),
      ];
    }

    $form['ui_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('UI Settings'),
    ];

    $form['ui_settings']['editor_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Editor Width'),
      '#description' => $this->t('Width of the image editor in pixels.'),
      '#default_value' => $config->get('editor_width') ?: 800,
      '#min' => 400,
      '#max' => 2000,
    ];

    $form['ui_settings']['editor_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Editor Height'),
      '#description' => $this->t('Height of the image editor in pixels.'),
      '#default_value' => $config->get('editor_height') ?: 600,
      '#min' => 300,
      '#max' => 1500,
    ];

    $form['ui_settings']['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Editor Theme'),
      '#description' => $this->t('Choose the theme for the image editor.'),
      '#options' => [
        'white' => $this->t('White Theme'),
        'black' => $this->t('Black Theme'),
      ],
      '#default_value' => $config->get('theme') ?: 'white',
    ];

    $form['performance'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Performance Settings'),
    ];

    $form['performance']['max_file_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum File Size'),
      '#description' => $this->t('Maximum file size for images that can be edited (in MB).'),
      '#default_value' => $config->get('max_file_size') ?: 10,
      '#min' => 1,
      '#max' => 100,
      '#step' => 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $enabledTools = [];
    foreach ($form_state->getValue(['enabled_tools'], []) as $key => $enabled) {
      if ($enabled) {
        $enabledTools[] = $key;
      }
    }

    $this->config('toast_image_editor.settings')
      ->set('enabled_tools', $enabledTools)
      ->set('editor_width', (int) $form_state->getValue('editor_width'))
      ->set('editor_height', (int) $form_state->getValue('editor_height'))
      ->set('theme', $form_state->getValue('theme'))
      ->set('max_file_size', (int) $form_state->getValue('max_file_size'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns available tools and their descriptions.
   *
   * @return array
   *   Array of available tools.
   */
  protected function getAvailableTools(): array {
    return [
      'crop' => [
        'title' => $this->t('Crop'),
        'description' => $this->t('Crop images to desired dimensions.'),
      ],
      'flip' => [
        'title' => $this->t('Flip'),
        'description' => $this->t('Flip images horizontally or vertically.'),
      ],
      'rotation' => [
        'title' => $this->t('Rotation'),
        'description' => $this->t('Rotate images by specified angles.'),
      ],
      'draw' => [
        'title' => $this->t('Draw'),
        'description' => $this->t('Draw freehand on images.'),
      ],
      'shape' => [
        'title' => $this->t('Shape'),
        'description' => $this->t('Add geometric shapes to images.'),
      ],
      'icon' => [
        'title' => $this->t('Icon'),
        'description' => $this->t('Add icons to images.'),
      ],
      'text' => [
        'title' => $this->t('Text'),
        'description' => $this->t('Add text annotations to images.'),
      ],
      'mask' => [
        'title' => $this->t('Mask'),
        'description' => $this->t('Apply masks and overlays to images.'),
      ],
      'filter' => [
        'title' => $this->t('Filter'),
        'description' => $this->t('Apply filters and effects to images.'),
      ],
    ];
  }

}