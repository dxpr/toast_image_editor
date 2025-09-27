/**
 * @file
 * Toast Image Editor integration for Drupal - Vanilla JS version.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  let imageEditor = null;
  let originalImageUrl = null;

  /**
   * Initialize the Toast Image Editor.
   */
  Drupal.behaviors.toastImageEditor = {
    attach: function (context, settings) {
      // Only run if we have the settings and haven't already initialized
      if (!settings.toastImageEditor || imageEditor) {
        return;
      }

      const editorContainer = document.getElementById('toast-image-editor');
      if (!editorContainer || editorContainer.dataset.initialized) {
        return;
      }

      // Mark as initialized to prevent double initialization
      editorContainer.dataset.initialized = 'true';

      originalImageUrl = settings.toastImageEditor.imageUrl;

      if (!originalImageUrl) {
        console.warn('No image URL provided for Toast Image Editor');
        return;
      }

      console.log('Initializing Toast Image Editor with image:', originalImageUrl);
      initializeEditor(settings.toastImageEditor);

      // Attach form submit handler after editor is initialized
      setTimeout(() => {
        attachFormSubmitHandler(settings.toastImageEditor);
      }, 1000);

      // Handle image editor changes - mark form as changed
      imageEditor.on('undoStackChanged', function(length) {
        if (length > 0) {
          // Mark the main Drupal form as changed
          const mainForm = document.querySelector('form[data-drupal-selector*="media"]');
          if (mainForm) {
            mainForm.classList.add('has-unsaved-changes');
          }
        }
      });
    }
  };

  /**
   * Initialize the Toast Image Editor with the specified configuration.
   */
  function initializeEditor(config) {
    const editorContainer = document.getElementById('toast-image-editor');
    if (!editorContainer) {
      console.error('Toast Image Editor container not found');
      return;
    }

    try {
      console.log('Creating Toast Image Editor...');

      // Initialize the editor with minimal configuration
      imageEditor = new tui.ImageEditor('#toast-image-editor', {
        includeUI: {
          loadImage: {
            path: originalImageUrl,
            name: 'EditableImage'
          },
          uiSize: {
            width: config.width || "100%",
            height: config.height || "600px",
          },
          menuBarPosition: 'bottom'
        },
        cssMaxWidth: 1000,
        cssMaxHeight: 800,
        usageStatistics: false
      });

      // Hide disabled tools after initialization
      setTimeout(() => {
        hideDisabledTools(config.enabledTools);
      }, 500);

      // Add event listeners for tracking changes
      imageEditor.on('undoStackChanged', function(length) {
        // Store the current state so it can be saved with the form
        if (length > 0) {
          window.toastImageEditorHasChanges = true;
        }
      });

      // Make imageEditor globally accessible for debugging
      window.imageEditor = imageEditor;

      console.log('Toast Image Editor initialized successfully');
    } catch (error) {
      console.error('Failed to initialize Toast Image Editor:', error);
      console.error('Error details:', error.message);
      console.error('Stack:', error.stack);

      if (editorContainer) {
        editorContainer.innerHTML = '<div class="error">Failed to initialize image editor: ' + error.message + '</div>';
      }
    }
  }

  /**
   * Get the editor theme configuration.
   */
  function getEditorTheme(config) {
    const theme = config.theme || 'white';

    return {
      'common.bi.image': '',
      'common.bisize.width': '251px',
      'common.bisize.height': '21px',
      'common.backgroundImage': 'none',
      'common.backgroundColor': theme === 'white' ? '#ffffff' : '#1e1e1e',
      'common.border': theme === 'white' ? '1px solid #c1c1c1' : '1px solid #444444',
      'header.backgroundImage': 'none',
      'header.backgroundColor': theme === 'white' ? 'transparent' : '#2d2d2d',
      'loadButton.backgroundColor': theme === 'white' ? '#fff' : '#333',
      'downloadButton.backgroundColor': theme === 'white' ? '#fdba3b' : '#ff6b35',
      'downloadButton.border': theme === 'white' ? '1px solid #fdba3b' : '1px solid #ff6b35',
      'downloadButton.color': theme === 'white' ? '#fff' : '#fff',
      'menu.normalIcon.color': theme === 'white' ? '#8a8a8a' : '#cccccc',
      'menu.activeIcon.color': theme === 'white' ? '#555555' : '#ffffff',
      'menu.disabledIcon.color': theme === 'white' ? '#434343' : '#666666',
      'menu.hoverIcon.color': theme === 'white' ? '#e9e9e9' : '#444444',
      'submenu.backgroundColor': theme === 'white' ? '#1e1e1e' : '#ffffff',
      'submenu.partition.color': theme === 'white' ? '#858585' : '#3c3c3c',
      'submenu.normalIcon.color': theme === 'white' ? '#8a8a8a' : '#cccccc',
      'submenu.activeIcon.color': theme === 'white' ? '#e9e9e9' : '#333333'
    };
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
          console.log(`Hiding tool: ${tool}`);
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
      if (window.toastImageEditorHasChanges && imageEditor) {
        try {
          // Get the edited image data and store it in the hidden field
          const imageData = imageEditor.toDataURL();
          hiddenField.value = imageData;
        } catch (error) {
          console.error('Error getting image data:', error);
        }
      }
    });
  }

})(Drupal, drupalSettings, once);