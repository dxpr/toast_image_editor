/**
 * @file
 * Toast Image Editor integration for Drupal - Vanilla JS version.
 *
 * Save Workflow:
 * --------------
 * 1. Editor initializes when media edit form loads
 * 2. User makes edits (tracked via undoStackChanged event)
 * 3. When user clicks form "Save" button:
 *    a. JavaScript captures current editor state as base64 image data
 *    b. Data is stored in hidden form field 'toast_image_editor_data'
 *    c. Visual feedback shown (buttons disabled, status announced)
 *    d. Form submits normally
 * 4. Server-side processing occurs via MediaPresaveService
 * 5. Image file is updated and media revision created
 * 6. Normal Drupal form save completes
 *
 * All user-facing strings use Drupal.t() for translation support.
 * Status changes are announced to screen readers via aria-live regions.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  // Namespace for Toast Image Editor to avoid global pollution.
  Drupal.toastImageEditor = Drupal.toastImageEditor || {};
  Drupal.toastImageEditor.instance = null;
  Drupal.toastImageEditor.originalImageUrl = null;
  Drupal.toastImageEditor.hasChanges = false;

  /**
   * Helper function to announce status to screen readers.
   */
  function announceStatus(message) {
    const statusElement = document.getElementById('toast-image-editor-status');
    if (statusElement) {
      statusElement.textContent = message;
      // Clear after announcement
      setTimeout(() => {
        statusElement.textContent = '';
      }, 3000);
    }
  }

  /**
   * Helper function to show/hide loading indicator.
   */
  function setLoadingState(isLoading) {
    const loadingElement = document.getElementById('toast-image-editor-loading');
    if (loadingElement) {
      if (isLoading) {
        loadingElement.classList.remove('hidden');
        loadingElement.setAttribute('aria-busy', 'true');
      } else {
        loadingElement.classList.add('hidden');
        loadingElement.setAttribute('aria-busy', 'false');
      }
    }
  }

  /**
   * Initialize the Toast Image Editor.
   */
  Drupal.behaviors.toastImageEditor = {
    attach: function (context, settings) {
      // Only run if we have the settings and haven't already initialized
      if (!settings.toastImageEditor || Drupal.toastImageEditor.instance) {
        return;
      }

      const editorContainer = document.getElementById('toast-image-editor');
      if (!editorContainer || editorContainer.dataset.initialized) {
        return;
      }

      // Mark as initialized to prevent double initialization
      editorContainer.dataset.initialized = 'true';

      Drupal.toastImageEditor.originalImageUrl = settings.toastImageEditor.imageUrl;

      if (!Drupal.toastImageEditor.originalImageUrl) {
        announceStatus(Drupal.t('Cannot load image. Please refresh the page and try again.'));
        setLoadingState(false);
        return;
      }

      setLoadingState(true);
      initializeEditor(settings.toastImageEditor);

      // Wait for editor to be fully initialized before attaching form handler
      waitForEditorReady().then(() => {
        attachFormSubmitHandler(settings.toastImageEditor);
        setLoadingState(false);
        announceStatus(Drupal.t('Editor ready'));
      });
    }
  };

  /**
   * Initialize the Toast Image Editor with the specified configuration.
   */
  function initializeEditor(config) {
    const editorContainer = document.getElementById('toast-image-editor');
    if (!editorContainer) {
      announceStatus(Drupal.t('Cannot load editor. Please refresh the page.'));
      setLoadingState(false);
      return;
    }

    try {
      const containerHeight = parseInt(config.height, 10) || 600;
      const toolbarOverhead = 280;

      // Initialize the editor with minimal configuration
      Drupal.toastImageEditor.instance = new tui.ImageEditor('#toast-image-editor', {
        includeUI: {
          loadImage: {
            path: Drupal.toastImageEditor.originalImageUrl,
            name: 'EditableImage'
          },
          theme: getToastUITheme(config),
          uiSize: {
            width: config.width || "100%",
            height: config.height || "600px",
          },
          menuBarPosition: 'bottom'
        },
        cssMaxWidth: 1000,
        cssMaxHeight: Math.max(containerHeight - toolbarOverhead, 200),
        usageStatistics: false
      });

      // Add event listeners for tracking changes
      Drupal.toastImageEditor.instance.on('undoStackChanged', function(length) {
        if (length > 0) {
          Drupal.toastImageEditor.hasChanges = true;
          // Mark the main Drupal form as changed
          const mainForm = document.querySelector('form[data-drupal-selector*="media"]');
          if (mainForm) {
            mainForm.classList.add('has-unsaved-changes');
          }
        }
      });

    } catch (error) {
      const errorMessage = Drupal.t('Cannot start editor. Please refresh the page or contact your site administrator if this problem continues.');
      announceStatus(errorMessage);
      setLoadingState(false);

      if (editorContainer) {
        editorContainer.innerHTML = '<div class="error" role="alert">' + errorMessage + '</div>';
      }
    }
  }

  /**
   * Get the Toast UI Image Editor theme configuration.
   */
  function getToastUITheme(config) {
    const theme = config.theme || 'white';

    if (theme === 'black') {
      // Black theme configuration for Toast UI Image Editor
      return {
        'common.bi.image': '',
        'common.backgroundColor': '#1e1e1e',
        'common.border': '1px solid #444444',
        'header.backgroundColor': '#2d2d2d',
        'loadButton.backgroundColor': '#333',
        'loadButton.border': '1px solid #444',
        'loadButton.color': '#fff',
        'downloadButton.backgroundColor': '#ff6b35',
        'downloadButton.border': '1px solid #ff6b35',
        'downloadButton.color': '#fff',
        'menu.normalIcon.color': '#cccccc',
        'menu.activeIcon.color': '#ffffff',
        'menu.disabledIcon.color': '#666666',
        'menu.hoverIcon.color': '#444444',
        'submenu.backgroundColor': '#ffffff',
        'submenu.partition.color': '#3c3c3c',
        'submenu.normalIcon.color': '#cccccc',
        'submenu.activeIcon.color': '#333333'
      };
    } else {
      // White theme configuration for Toast UI Image Editor
      return {
        'common.bi.image': '',
        'common.backgroundColor': '#ffffff',
        'common.border': '1px solid #c1c1c1',
        'header.backgroundColor': 'transparent',
        'loadButton.backgroundColor': '#fff',
        'loadButton.border': '1px solid #c1c1c1',
        'loadButton.color': '#333',
        'downloadButton.backgroundColor': '#fdba3b',
        'downloadButton.border': '1px solid #fdba3b',
        'downloadButton.color': '#fff',
        'menu.normalIcon.color': '#8a8a8a',
        'menu.activeIcon.color': '#555555',
        'menu.disabledIcon.color': '#434343',
        'menu.hoverIcon.color': '#e9e9e9',
        'submenu.backgroundColor': '#1e1e1e',
        'submenu.partition.color': '#858585',
        'submenu.normalIcon.color': '#8a8a8a',
        'submenu.activeIcon.color': '#e9e9e9'
      };
    }
  }

  /**
   * Get enabled menus based on configuration.
   */
  function getEnabledMenus(enabledTools) {
    const allMenus = ['crop', 'flip', 'rotation', 'draw', 'shape', 'icon', 'text', 'mask', 'filter'];
    const enabledMenus = [];

    if (enabledTools && Array.isArray(enabledTools)) {
      enabledMenus.push(...enabledTools.filter(tool => allMenus.includes(tool)));
    } else if (enabledTools && typeof enabledTools === 'object') {
      allMenus.forEach(function(menu) {
        if (enabledTools[menu] === true) {
          enabledMenus.push(menu);
        }
      });
    } else {
      // Default to all menus if no configuration
      return allMenus;
    }

    return enabledMenus.length > 0 ? enabledMenus : allMenus;
  }

  /**
   * Wait for editor to be fully ready using MutationObserver.
   */
  function waitForEditorReady() {
    return new Promise((resolve) => {
      if (!Drupal.toastImageEditor.instance) {
        resolve();
        return;
      }

      // Check if the editor's UI is already rendered
      const checkReady = () => {
        const menuBar = document.querySelector('.tui-image-editor-menu');
        if (menuBar) {
          // Hide disabled tools once UI is ready
          const config = drupalSettings.toastImageEditor;
          if (config && config.enabledTools) {
            hideDisabledTools(config.enabledTools);
          }
          resolve();
          return true;
        }
        return false;
      };

      // Try immediately first
      if (checkReady()) {
        return;
      }

      // If not ready, observe for changes
      const observer = new MutationObserver(() => {
        if (checkReady()) {
          observer.disconnect();
        }
      });

      const editorContainer = document.getElementById('toast-image-editor');
      if (editorContainer) {
        observer.observe(editorContainer, {
          childList: true,
          subtree: true
        });

        // Fallback timeout after 5 seconds
        setTimeout(() => {
          observer.disconnect();
          resolve();
        }, 5000);
      } else {
        resolve();
      }
    });
  }

  /**
   * Hide disabled tools from the toolbar.
   */
  function hideDisabledTools(enabledTools) {
    if (!enabledTools || !Array.isArray(enabledTools)) {
      return;
    }

    const allTools = ['resize', 'crop', 'flip', 'rotate', 'draw', 'shape', 'icon', 'text', 'mask', 'filter'];
    const toolMapping = {
      'rotation': 'rotate'
    };

    allTools.forEach(function(tool) {
      const mappedTool = toolMapping[tool] || tool;
      const isEnabled = enabledTools.includes(tool) || enabledTools.includes(mappedTool);

      if (!isEnabled) {
        const toolButton = document.querySelector(`.tie-btn-${tool}`);
        if (toolButton) {
          toolButton.style.display = 'none';
        }
      }
    });
  }

  /**
   * Hook into the main form submission to save image changes.
   */
  function attachFormSubmitHandler(config) {
    const mainForm = document.querySelector('form[data-drupal-selector*="media"]');
    if (!mainForm) {
      return;
    }

    // Add a hidden field to store image data
    let hiddenField = mainForm.querySelector('input[name="toast_image_editor_data"]');
    if (!hiddenField) {
      hiddenField = document.createElement('input');
      hiddenField.type = 'hidden';
      hiddenField.name = 'toast_image_editor_data';
      mainForm.appendChild(hiddenField);
    }

    // Listen for form submission
    mainForm.addEventListener('submit', function(e) {
      if (Drupal.toastImageEditor.hasChanges && Drupal.toastImageEditor.instance) {
        try {
          // Show saving indicator
          announceStatus(Drupal.t('Saving your changes...'));

          // Get the edited image data and store it in the hidden field
          const imageData = Drupal.toastImageEditor.instance.toDataURL();
          hiddenField.value = imageData;

        } catch (error) {
          e.preventDefault();
          const errorMessage = Drupal.t('Cannot save your changes. Please try again or contact your site administrator.');
          announceStatus(errorMessage);

          // Show error to user
          if (typeof Drupal.Message !== 'undefined') {
            new Drupal.Message().add(errorMessage, {type: 'error'});
          } else {
            alert(errorMessage);
          }
        }
      }
    });
  }

})(Drupal, drupalSettings, once);