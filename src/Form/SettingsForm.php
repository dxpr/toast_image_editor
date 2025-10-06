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
    TypedConfigManagerInterface $typedConfigManager,
    protected ModuleExtensionList $extensionListModule,
    protected string $appRoot,
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
      $container->getParameter('app.root'),
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
      '#title' => $this->t('Editing tools'),
      '#description' => $this->t('Choose which tools appear in the editor toolbar.'),
    ];

    $tools = $this->getAvailableTools();
    $enabledTools = $config->get('enabled_tools') ?: array_keys($tools);

    // Create a container for the checkboxes grid.
    $form['tools']['enabled_tools'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Select tools'),
      '#title_display' => 'invisible',
      '#options' => [],
      '#default_value' => $enabledTools,
      '#attributes' => [
        'class' => ['toast-image-editor-tools-grid'],
      ],
    ];

    // Build options with icons and add individual descriptions.
    foreach ($tools as $key => $tool) {
      $icon = $this->getToolIcon($key);
      $form['tools']['enabled_tools']['#options'][$key] = Markup::create($icon . ' ' . $tool['title']);

      // Add description for each checkbox for better accessibility.
      $form['tools']['enabled_tools'][$key]['#description'] = $tool['description'];
    }

    // Add comprehensive help text.
    $form['tools']['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Uncheck tools to hide them from the toolbar. Keep at least one tool selected.'),
      '#attributes' => [
        'class' => ['description'],
      ],
    ];

    $form['ui_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display settings'),
    ];

    $form['ui_settings']['editor_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Width'),
      '#description' => $this->t('Set width in pixels, or leave at 0 for full width.'),
      '#default_value' => (int) $config->get('editor_width') ?: 0,
      '#field_suffix' => $this->t('px'),
      '#min' => 0,
      '#max' => 2000,
    ];

    $form['ui_settings']['editor_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Editor height in pixels.'),
      '#default_value' => (int) $config->get('editor_height') ?: 600,
      '#field_suffix' => $this->t('px'),
      '#min' => 300,
      '#max' => 1500,
    ];

    $form['ui_settings']['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#description' => $this->t('Choose light or dark appearance.'),
      '#options' => [
        'white' => $this->t('Light'),
        'black' => $this->t('Dark'),
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
        'description' => $this->t('Trim images to specific dimensions or aspect ratios.'),
      ],
      'flip' => [
        'title' => $this->t('Flip'),
        'description' => $this->t('Mirror images horizontally or vertically.'),
      ],
      'rotation' => [
        'title' => $this->t('Rotate'),
        'description' => $this->t('Turn images to any angle.'),
      ],
      'draw' => [
        'title' => $this->t('Draw'),
        'description' => $this->t('Sketch freehand lines and marks.'),
      ],
      'shape' => [
        'title' => $this->t('Shape'),
        'description' => $this->t('Insert circles, rectangles, and triangles.'),
      ],
      'icon' => [
        'title' => $this->t('Icon'),
        'description' => $this->t('Place icons and symbols.'),
      ],
      'text' => [
        'title' => $this->t('Text'),
        'description' => $this->t('Add text labels and captions.'),
      ],
      'mask' => [
        'title' => $this->t('Mask'),
        'description' => $this->t('Apply overlay effects and masks.'),
      ],
      'filter' => [
        'title' => $this->t('Filter'),
        'description' => $this->t('Enhance images with color and effect filters.'),
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
    $iconPath = $this->appRoot . '/' . $modulePath . '/assets/icons/ic-' . ($tool === 'rotation' ? 'rotate' : $tool) . '.svg';

    // Check if the SVG file exists and load it.
    if (file_exists($iconPath)) {
      $svgContent = file_get_contents($iconPath);
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
