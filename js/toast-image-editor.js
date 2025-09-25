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

      // Handle save button click
      const saveButton = document.getElementById('save-edited-image');
      if (saveButton && !saveButton.dataset.listenerAdded) {
        saveButton.dataset.listenerAdded = 'true';
        saveButton.addEventListener('click', function(e) {
          e.preventDefault();
          saveEditedImage(settings.toastImageEditor);
        });
      }
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
          initMenu: 'crop',
          uiSize: {
            width: '100%',
            height: '600px'
          },
          menuBarPosition: 'bottom'
        },
        cssMaxWidth: 1000,
        cssMaxHeight: 800,
        usageStatistics: false
      });

      // Show the save button once editor is initialized
      const saveButton = document.getElementById('save-edited-image');
      if (saveButton) {
        saveButton.style.display = 'block';
      }

      // Add event listeners
      imageEditor.on('undoStackChanged', function(length) {
        const saveBtn = document.getElementById('save-edited-image');
        if (saveBtn && length > 0) {
          saveBtn.disabled = false;
          saveBtn.classList.remove('is-disabled');
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
   * Save the edited image.
   */
  function saveEditedImage(config) {
    if (!imageEditor) {
      console.error('Image editor not initialized');
      return;
    }

    try {
      // Show loading state
      const saveButton = document.getElementById('save-edited-image');
      if (!saveButton) {
        console.error('Save button not found');
        return;
      }

      const originalText = saveButton.value;
      saveButton.value = 'Saving...';
      saveButton.disabled = true;
      saveButton.classList.add('is-disabled');

      // Get the edited image data
      const imageData = imageEditor.toDataURL();

      // Prepare form data
      const formData = new FormData();
      formData.append('imageData', imageData);

      // Add CSRF token if available
      const tokenElement = document.querySelector('meta[name="csrf-token"]');
      if (tokenElement) {
        formData.append('_token', tokenElement.getAttribute('content'));
      }

      // Send fetch request to save the image
      fetch(config.saveUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Show success message
          if (Drupal.announce) {
            Drupal.announce(Drupal.t('Image saved successfully'));
          }

          // Optionally redirect or reload
          if (data.redirect) {
            window.location.href = data.redirect;
          } else {
            // Reset the save button and show success
            saveButton.value = 'Saved!';
            saveButton.classList.remove('is-disabled');
            setTimeout(() => {
              saveButton.value = originalText;
              saveButton.disabled = false;
            }, 2000);
          }
        } else {
          throw new Error(data.message || 'Failed to save image');
        }
      })
      .catch(error => {
        console.error('Error saving image:', error);
        if (Drupal.announce) {
          Drupal.announce(Drupal.t('Error saving image: @error', {'@error': error.message}));
        }

        // Reset button state
        saveButton.value = originalText;
        saveButton.disabled = false;
        saveButton.classList.remove('is-disabled');
      });
    } catch (error) {
      console.error('Failed to save edited image:', error);
      if (Drupal.announce) {
        Drupal.announce(Drupal.t('Failed to save image'));
      }
    }
  }

})(Drupal, drupalSettings, once);