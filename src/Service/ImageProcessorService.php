<?php

declare(strict_types=1);

namespace Drupal\toast_image_editor\Service;

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
      $sourceField = $media->getSource()->getSourceFieldDefinition($media->bundle->entity);
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

      // Decode base64 image data
      $decodedData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
      if ($decodedData === FALSE) {
        $this->logger->error('Invalid base64 image data for media @id.', ['@id' => $media->id()]);
        return FALSE;
      }

      // Create a new revision
      $media->setNewRevision(TRUE);
      $media->setRevisionLogMessage('Image edited with Toast Image Editor');

      // Save the new image data to the existing file
      $uri = $fileEntity->getFileUri();
      if ($this->fileSystem->saveData($decodedData, $uri, FileSystemInterface::EXISTS_REPLACE) === FALSE) {
        $this->logger->error('Failed to save edited image data for media @id.', ['@id' => $media->id()]);
        return FALSE;
      }

      // Update file size
      $fileEntity->setSize(strlen($decodedData));
      $fileEntity->save();

      // Save the media entity with new revision
      $media->save();

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
    // Check if it's an image media type
    $sourcePlugin = $media->getSource();
    if ($sourcePlugin->getPluginId() !== 'image') {
      return FALSE;
    }

    $sourceField = $sourcePlugin->getSourceFieldDefinition($media->bundle->entity);
    $fieldName = $sourceField->getName();

    if (!$media->hasField($fieldName) || $media->get($fieldName)->isEmpty()) {
      return FALSE;
    }

    $fileEntity = $media->get($fieldName)->entity;
    if (!$fileEntity instanceof FileInterface) {
      return FALSE;
    }

    // Check if file exists and is readable
    $uri = $fileEntity->getFileUri();
    return $this->fileSystem->realpath($uri) && is_readable($this->fileSystem->realpath($uri));
  }

}