<?php

declare(strict_types=1);

namespace Drupal\toast_image_editor\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Render\Markup;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for Toast Image Editor settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The settings configuration key for the Toast Image Editor module.
   *
   * @var string
   */
  const SETTINGS = 'toast_image_editor.settings';

  /**
   * {@inheritdoc}
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected TypedConfigManagerInterface $typedConfigManager,
    protected ModuleExtensionList $extensionListModule,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('extension.list.module'),
    );
  }

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
    return [self::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(self::SETTINGS);

    $form['tools'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Available Tools'),
      '#description' => $this->t('Select which tools should be available in the Toast Image Editor.'),
    ];

    $tools = $this->getAvailableTools();
    $enabledTools = $config->get('enabled_tools') ?: array_keys($tools);

    // Create a container for the checkboxes grid.
    $form['tools']['enabled_tools'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available Tools'),
      '#title_display' => 'invisible',
      '#options' => [],
      '#default_value' => $enabledTools,
      '#attributes' => [
        'class' => ['toast-image-editor-tools-grid'],
      ],
    ];

    // Build options with icons.
    foreach ($tools as $key => $tool) {
      $icon = $this->getToolIcon($key);
      $form['tools']['enabled_tools']['#options'][$key] = Markup::create($icon . ' ' . $tool['title']);
    }

    $form['ui_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('UI Settings'),
    ];

    $form['ui_settings']['editor_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Editor Width'),
      '#description' => $this->t('Width of the image editor in pixels.<br>Leave blank for full width (100%).'),
      '#default_value' => (int) $config->get('editor_width') ?: 0,
      '#field_suffix' => $this->t('px'),
      '#min' => 0,
      '#max' => 2000,
    ];

    $form['ui_settings']['editor_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Editor Height'),
      '#description' => $this->t('Height of the image editor in pixels.'),
      '#default_value' => (int) $config->get('editor_height') ?: 600,
      '#field_suffix' => $this->t('px'),
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

    // Attach CSS for the grid layout.
    $form['#attached']['library'][] = 'toast_image_editor/toast-image-editor-settings';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Get enabled tools from the checkboxes array.
    $enabledTools = array_filter($form_state->getValue('enabled_tools', []));

    $this->config('toast_image_editor.settings')
      ->set('enabled_tools', array_keys($enabledTools))
      ->set('editor_width', (int) $form_state->getValue('editor_width'))
      ->set('editor_height', (int) $form_state->getValue('editor_height'))
      ->set('theme', $form_state->getValue('theme'))
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

  /**
   * Get icon for a tool.
   *
   * @param string $tool
   *   Tool name.
   *
   * @return string
   *   Icon markup.
   */
  protected function getToolIcon(string $tool): string {
    $modulePath = $this->extensionListModule->getPath('toast_image_editor');
    $iconPath = $modulePath . '/assets/icons/ic-' . ($tool === 'rotation' ? 'rotate' : $tool) . '.svg';

    // Check if the SVG file exists.
    if (file_exists(DRUPAL_ROOT . '/' . $iconPath)) {
      $svgContent = file_get_contents(DRUPAL_ROOT . '/' . $iconPath);
      if ($svgContent !== FALSE) {
        // Clean up the SVG and add classes.
        $svgContent = preg_replace('/<svg/', '<svg class="tool-icon-svg"', $svgContent, 1);
        return '<span class="tool-icon">' . $svgContent . '</span>';
      }
    }

    // Fallback to text icons if SVG not found.
    $fallbackIcons = [
      'crop' => '✂',
      'flip' => '↔',
      'rotation' => '↻',
      'draw' => '✏',
      'shape' => '▢',
      'icon' => '★',
      'text' => 'T',
      'mask' => '◐',
      'filter' => '⚡',
    ];

    return '<span class="tool-icon">' . ($fallbackIcons[$tool] ?? '●') . '</span>';
  }

  /**
   * Retrieves the default set of tools with their enabled status.
   *
   * @return array
   *   An associative array where each key represents a tool name and
   *   its value indicates whether the tool is enabled (TRUE) or not (FALSE).
   */
  public static function defaultTools(): array {
    return [
      'crop' => TRUE,
      'flip' => TRUE,
      'rotation' => TRUE,
      'draw' => TRUE,
      'shape' => TRUE,
      'icon' => TRUE,
      'text' => TRUE,
      'mask' => TRUE,
      'filter' => TRUE,
    ];
  }

}
