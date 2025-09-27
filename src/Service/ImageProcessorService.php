<?php

declare(strict_types=1);

namespace Drupal\toast_image_editor\Service;

use Drupal\Core\File\FileExists;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaTypeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for processing and saving edited images.
 */
class ImageProcessorService {

  /**
   * Constructs the ImageProcessorService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file url generator service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected LoggerChannelInterface $logger,
    protected ConfigFactoryInterface $configFactory,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected RequestStack $requestStack,
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
      if (!$mediaType instanceof MediaTypeInterface) {
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
      $media->setNewRevision();
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
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function canEditMedia(MediaInterface $media): bool {
    // Check if it's an image media type.
    $sourcePlugin = $media->getSource();
    if ($sourcePlugin->getPluginId() !== 'image') {
      return FALSE;
    }

    $mediaType = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    if (!$mediaType instanceof MediaTypeInterface) {
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

    // Check if a file exists and is readable.
    $uri = $fileEntity->getFileUri();
    return $this->fileSystem->realpath($uri) && is_readable($this->fileSystem->realpath($uri));
  }

  /**
   * Helper function to get image URL for the media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity for which to generate the image URL.
   *
   * @return string|null
   *   The absolute URL of the image file if available, or NULL if the media
   *   entity does not have a valid image file.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getImageUrl(MediaInterface $media): ?string {
    $mediaType = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    if (!$mediaType instanceof MediaTypeInterface) {
      return NULL;
    }

    $sourceField = $media->getSource()->getSourceFieldDefinition($mediaType);
    $fieldName = $sourceField->getName();

    if (!$media->hasField($fieldName) || $media->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $file = $media->get($fieldName)->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }

    // Generate relative URL first, then convert to absolute with the current
    // request base.
    $fileUrl = $this->fileUrlGenerator->generateString($file->getFileUri());

    // Get the current request to ensure we use the correct domain.
    $request = $this->requestStack->getCurrentRequest();
    $host = $request->getHttpHost();
    if ($host) {
      $scheme = $request->getScheme();
      $baseUrl = $scheme . '://' . $host;

      // Convert relative URL to absolute.
      if (str_starts_with($fileUrl, '/')) {
        return $baseUrl . $fileUrl;
      }
    }

    // Fallback to the service method.
    return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
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
    $metric = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
    $value = (int) $memoryLimit;

    if ($metric === 'k') {
      $value *= 1024;
    }

    if ($metric === 'm') {
      $value *= (1024 * 1024);
    }

    if ($metric === 'g') {
      $value *= (1024 * 1024 * 1024);
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
