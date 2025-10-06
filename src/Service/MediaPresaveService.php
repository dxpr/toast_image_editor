<?php

declare(strict_types=1);

namespace Drupal\toast_image_editor\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for handling media presave operations.
 */
class MediaPresaveService {

  use StringTranslationTrait;

  /**
   * Constructs the MediaPresaveService.
   *
   * @param \Drupal\toast_image_editor\Service\ImageProcessorService $imageProcessor
   *   The image processor service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected ImageProcessorService $imageProcessor,
    protected RequestStack $requestStack,
    protected LoggerChannelInterface $logger,
    protected AccountProxyInterface $currentUser,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
  ) {}

  /**
   * Processes image editor data on media entity presave.
   *
   * This hook intercepts media saves to process image editor changes.
   * The workflow integration:
   * 1. Checks if media entity has associated image editor data in request
   * 2. Validates user permissions and media access
   * 3. Calls ImageProcessorService to decode and save the edited image
   * 4. Creates a new media revision with updated file
   * 5. Returns control to normal save process.
   *
   * Note: This happens during presave, so the media entity itself
   * is saved by the calling form/process after this completes.
   *
   * @param \Drupal\media\MediaInterface $entity
   *   The media entity being saved.
   *
   * @return bool
   *   TRUE if processing was successful or not needed, FALSE on error.
   *
   * @see \Drupal\toast_image_editor\Service\ImageProcessorService::saveEditedImage()
   */
  public function processMediaPresave(MediaInterface $entity): bool {
    // Verify it's an image media type.
    if (!$this->imageProcessor->canEditMedia($entity)) {
      return TRUE;
    }

    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return TRUE;
    }

    // Try to get image data from request (form submission).
    $imageData = $request->request->get('toast_image_editor_data');

    // Also check in POST data with field name format.
    if (empty($imageData)) {
      $imageData = $request->request->get('toast_image_editor_data[0][value]');
    }

    // Check all request parameters.
    if (empty($imageData)) {
      $allParams = $request->request->all();
      foreach ($allParams as $key => $value) {
        if (str_contains($key, 'toast_image_editor_data')) {
          if (is_array($value)) {
            $imageData = $value[0]['value'] ?? $value['value'] ?? '';
          }
          else {
            $imageData = $value;
          }
          break;
        }
      }
    }

    if (empty($imageData)) {
      return TRUE;
    }

    if (!str_starts_with($imageData, 'data:image/')) {
      return TRUE;
    }

    $this->logger->info('Found valid image data for media @id, size: @size bytes', [
      '@id' => $entity->id(),
      '@size' => strlen($imageData),
    ]);

    // Check permissions.
    if (!$this->currentUser->hasPermission('use toast image editor')) {
      $this->logger->warning('User @uid attempted to save edited image for media @id without permission.', [
        '@uid' => $this->currentUser->id(),
        '@id' => $entity->id(),
      ]);
      return FALSE;
    }

    // Verify user has access to update the media entity.
    if (!$entity->access('update', $this->currentUser)) {
      $this->logger->warning('User @uid attempted to save edited image for media @id without update access.', [
        '@uid' => $this->currentUser->id(),
        '@id' => $entity->id(),
      ]);
      return FALSE;
    }

    try {
      // Process the edited image data (without saving the media entity).
      $result = $this->imageProcessor->saveEditedImage($entity, $imageData);

      if ($result['success']) {
        $this->logger->info('Image updated for media @id by user @uid', [
          '@id' => $entity->id(),
          '@uid' => $this->currentUser->id(),
        ]);
        // Show success message to user.
        $this->messenger->addStatus($result['message']);
      }
      else {
        // Show error message to user.
        $this->messenger->addError($result['message']);
      }

      return $result['success'];
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to save edited image for media @id: @message', [
        '@id' => $entity->id(),
        '@message' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('Unexpected error while saving. Please try again or contact your site administrator.'));
      return FALSE;
    }
  }

}
