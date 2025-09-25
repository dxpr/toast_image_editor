<?php

declare(strict_types=1);

namespace Drupal\toast_image_editor\Service;

use Drupal\Core\File\FileExists;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Service for processing and saving edited images.
 */
class ImageProcessorService {

  /**
   * Constructs the ImageProcessorService.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected LoggerChannelInterface $logger,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Saves the edited image data and creates a new media revision.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to update.
   * @param string $imageData
   *   Base64 encoded image data.
   *
   * @return bool
   *   TRUE if the image was saved successfully, FALSE otherwise.
   */
  public function saveEditedImage(MediaInterface $media, string $imageData): bool {
    try {
      $mediaType = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
      if (!$mediaType) {
        $this->logger->error('Media type not found for media @id.', ['@id' => $media->id()]);
        return FALSE;
      }
      $sourceField = $media->getSource()->getSourceFieldDefinition($mediaType);
      $fieldName = $sourceField->getName();

      if (!$media->hasField($fieldName) || $media->get($fieldName)->isEmpty()) {
        $this->logger->error('Media entity @id does not have a valid source field.', ['@id' => $media->id()]);
        return FALSE;
      }

      $fileEntity = $media->get($fieldName)->entity;
      if (!$fileEntity instanceof FileInterface) {
        $this->logger->error('Could not load file entity for media @id.', ['@id' => $media->id()]);
        return FALSE;
      }

      // Extract and decode base64 image data with memory optimization.
      $base64Data = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);

      // Check base64 data length to prevent memory issues.
      $estimatedSize = (strlen($base64Data) * 3) / 4;
      $memoryLimit = ini_get('memory_limit');
      $memoryLimitBytes = $this->convertToBytes($memoryLimit);

      if ($estimatedSize > ($memoryLimitBytes * 0.5)) {
        $this->logger->error('Image data too large for media @id. Estimated size: @size bytes, Memory limit: @limit', [
          '@id' => $media->id(),
          '@size' => $estimatedSize,
          '@limit' => $memoryLimit,
        ]);
        return FALSE;
      }

      $decodedData = base64_decode($base64Data, TRUE);

      // Free up memory immediately.
      unset($base64Data, $imageData);

      if ($decodedData === FALSE || $decodedData === '') {
        $this->logger->error('Invalid base64 image data for media @id.', ['@id' => $media->id()]);
        return FALSE;
      }

      // Create a new revision.
      $media->setNewRevision(TRUE);
      $media->setRevisionLogMessage('Image edited with Toast Image Editor');

      // Save the new image data to the existing file.
      $uri = $fileEntity->getFileUri();
      $result = $this->fileSystem->saveData($decodedData, $uri, FileExists::Replace);
      if (!$result) {
        $this->logger->error('Failed to save edited image data for media @id.', ['@id' => $media->id()]);
        return FALSE;
      }

      // Update file size.
      $fileEntity->setSize(strlen($decodedData));
      $fileEntity->save();

      // Clear image style cache for this image.
      $this->clearImageStyleCache($uri);

      // Note: Don't save the media entity here to avoid recursion.
      // The media entity will be saved by the calling form/process.
      $this->logger->info('Successfully saved edited image for media @id.', ['@id' => $media->id()]);
      return TRUE;
    }
    catch (\Exception $e) {
      $this->logger->error('Error saving edited image for media @id: @message', [
        '@id' => $media->id(),
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Validates that a media entity can be edited.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity to validate.
   *
   * @return bool
   *   TRUE if the media can be edited, FALSE otherwise.
   */
  public function canEditMedia(MediaInterface $media): bool {
    // Check if it's an image media type.
    $sourcePlugin = $media->getSource();
    if ($sourcePlugin->getPluginId() !== 'image') {
      return FALSE;
    }

    $mediaType = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    if (!$mediaType) {
      return FALSE;
    }
    $sourceField = $sourcePlugin->getSourceFieldDefinition($mediaType);
    $fieldName = $sourceField->getName();

    if (!$media->hasField($fieldName) || $media->get($fieldName)->isEmpty()) {
      return FALSE;
    }

    $fileEntity = $media->get($fieldName)->entity;
    if (!$fileEntity instanceof FileInterface) {
      return FALSE;
    }

    // Check if file exists and is readable.
    $uri = $fileEntity->getFileUri();
    return $this->fileSystem->realpath($uri) && is_readable($this->fileSystem->realpath($uri));
  }

  /**
   * Convert memory limit string to bytes.
   *
   * @param string $memoryLimit
   *   Memory limit string (e.g., '512M', '1G').
   *
   * @return int
   *   Memory limit in bytes.
   */
  private function convertToBytes(string $memoryLimit): int {
    $memoryLimit = trim($memoryLimit);
    $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
    $value = (int) $memoryLimit;

    switch ($last) {
      case 'g':
        $value *= 1024;
      case 'm':
        $value *= 1024;
      case 'k':
        $value *= 1024;
    }

    return $value;
  }

  /**
   * Clear image style cache for a given image URI.
   *
   * @param string $uri
   *   The file URI to clear cache for.
   */
  private function clearImageStyleCache(string $uri): void {
    try {
      /** @var \Drupal\image\ImageStyleStorageInterface $imageStyleStorage */
      $imageStyleStorage = $this->entityTypeManager->getStorage('image_style');
      $imageStyles = $imageStyleStorage->loadMultiple();

      foreach ($imageStyles as $imageStyle) {
        /** @var \Drupal\image\ImageStyleInterface $imageStyle */
        $imageStyle->flush($uri);
      }

      $this->logger->info('Cleared image style cache for URI: @uri', ['@uri' => $uri]);
    }
    catch (\Exception $e) {
      $this->logger->warning('Failed to clear image style cache for URI @uri: @message', [
        '@uri' => $uri,
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
