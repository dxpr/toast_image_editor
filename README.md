# Toast Image Editor for Drupal

This module integrates Toast UI Image Editor with Drupal, providing a powerful image editing interface for media entities.

## Features

- Full-featured image editor using Toast UI Image Editor
- White and black theme support
- Configurable editor dimensions
- Tool selection (crop, flip, rotate, draw, text, etc.)
- Offline-compatible (no CDN dependencies)

## Installation

1. Install the module in your Drupal site
2. Run the build process to copy assets locally:
   ```bash
   cd web/modules/custom/toast_image_editor
   npm install
   ```
3. Enable the module
4. Configure settings at `/admin/config/media/toast-image-editor`

## Build Process

This module uses a local build process to avoid CDN dependencies for firewall/offline environments:

- `npm install` - Downloads dependencies and automatically runs the build
- `npm run build` - Copies assets from node_modules to local assets/ directory

## Dependencies

- tui-image-editor: ^3.15.3
- fabric: ^4.4.0
- file-saver: ^1.3.8
- tui-code-snippet: ^1.5.2
- tui-color-picker: ^2.2.7

## Dependabot

Dependabot is configured to automatically create PRs for tui-image-editor updates only (not subdependencies).

## Development

The `scripts/build-assets.js` script handles copying necessary files from node_modules to the assets/ directory. All cloud URLs have been replaced with local asset references.