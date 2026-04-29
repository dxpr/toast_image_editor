> Part of [DXPR CMS](https://dxpr.com/c/marketing-cms) -- The AI-Powered Drupal CMS
>
> [Documentation](https://dxpr.com/docs) | [Try Free](https://dxpr.com/try) | [dxpr.com](https://dxpr.com)

# Toast Image Editor -- Professional Drupal Image Editing with Revision Support

Professional image editing capabilities for Drupal media with revision
support and offline compatibility.

## Features

- **Full Image Editor**: Crop, rotate, flip, draw, add text and shapes
- **Revision Support**: Creates new media revisions preserving original images
- **Theme Selection**: White and black theme options for different workflows
- **Configurable Tools**: Enable/disable specific editing tools per requirements
- **Offline-ready**: All assets served locally, no CDN dependencies
- **Responsive Interface**: Configurable editor dimensions
- **Filters & Effects**: Apply filters, adjust brightness, contrast, and more
- **Undo/Redo Support**: Full editing history with keyboard shortcuts

## Requirements

- Drupal 10.3+ or 11.0+
- Media module (core)
- File module (core)
- PHP 8.1+

## Installation

```bash
composer require drupal/toast_image_editor
drush en toast_image_editor
```

## Configuration

1. Configure editor settings at `/admin/config/media/toast-image-editor`
2. Set permissions at `/admin/people/permissions#module-toast_image_editor`
3. Select which tools to enable (crop, draw, text, filters, etc.)
4. Choose theme (white or black)
5. Configure editor dimensions

## Usage

### For Content Editors
1. Navigate to Media library (`/admin/content/media`)
2. Select an image media item
3. Click "Edit with Toast Editor" button
4. Use the editing tools to modify the image
5. Click "Save" to create a new revision

### For Site Builders
1. The module automatically adds an edit action to image media types
2. Configure which roles can edit images via permissions
3. Customize editor toolbar through configuration


## Editing Tools

### Drawing Tools
- Free drawing with customizable brush size and color
- Straight line drawing
- Text overlay with font customization
- Shapes (rectangle, circle, triangle)

### Image Manipulation
- Crop with aspect ratio options
- Rotate (90°, -90°, custom angles)
- Flip horizontal/vertical
- Resize

### Filters & Adjustments
- Grayscale, sepia, blur, sharpen
- Brightness and contrast adjustment
- Color filters
- Custom filter combinations

## Permission Configuration

The module provides granular permissions:
- Access Toast Image Editor
- Edit images with Toast Image Editor
- Configure Toast Image Editor settings

## Technical Details

### Asset Management
- All JavaScript and CSS assets served locally
- No external CDN dependencies
- Automatic asset copying during installation
- Versioned assets for cache management

### Revision Handling
- Creates new media revisions on save
- Preserves original image files
- Maintains revision history
- Rollback capability through Media revisions

### JavaScript Libraries
- tui-image-editor: ^3.15.3
- fabric: ^4.4.0 (canvas manipulation)
- file-saver: ^1.3.8 (download functionality)
- tui-code-snippet: ^1.5.2
- tui-color-picker: ^2.2.7


## Development

Run code quality checks using Docker:

```bash
# Check code standards
docker compose run --rm drupal-lint

# Auto-fix code standards
docker compose run --rm drupal-lint-auto-fix

# Check for deprecations
docker compose run --rm drupal-check
```

## Troubleshooting

### Common Issues

**Editor not loading:**
- Ensure `npm install` was run successfully
- Check browser console for JavaScript errors
- Verify assets exist in `assets/` directory
- Clear Drupal and browser caches

**Permission denied errors:**
- Verify user has "Edit images with Toast Image Editor" permission
- Check media entity access permissions
- Ensure file system permissions allow media creation


## Similar Projects

- [Image Widget Crop](https://www.drupal.org/project/image_widget_crop) - Crop-only functionality
- [Focal Point](https://www.drupal.org/project/focal_point) - Smart image cropping
- [ImageMagick](https://www.drupal.org/project/imagemagick) - Server-side image processing

## Related DXPR Modules

- [DXPR Builder](https://www.drupal.org/project/dxpr_builder) -- Drag-and-drop Drupal page builder
- [AI Image Alt Text](https://www.drupal.org/project/ai_image_alt_text) -- AI-generated alt text for Drupal images
- [CKEditor AI Agent](https://www.drupal.org/project/ckeditor_ai_agent) -- AI-powered content creation in the Drupal editor
