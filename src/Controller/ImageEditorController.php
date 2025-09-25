<?php

declare(strict_types=1);

namespace Drupal\toast_image_editor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\toast_image_editor\Service\ImageProcessorService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for handling image editor operations.
 */
class ImageEditorController extends ControllerBase {

  /**
   * Constructs the ImageEditorController.
   */
  public function __construct(
    protected ImageProcessorService $imageProcessor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('toast_image_editor.image_processor'),
    );
  }

  /**
   * Saves the edited image.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response indicating success or failure.
   */
  public function save(Request $request, MediaInterface $media): JsonResponse {
    // Verify permissions
    if (!$this->currentUser()->hasPermission('edit media images') || !$media->access('update')) {
      return new JsonResponse(['success' => FALSE, 'message' => $this->t('Access denied')], 403);
    }

    // Verify media can be edited
    if (!$this->imageProcessor->canEditMedia($media)) {
      return new JsonResponse(['success' => FALSE, 'message' => $this->t('This media cannot be edited')], 400);
    }

    // Get image data from request
    $imageData = $request->request->get('imageData');
    if (empty($imageData)) {
      return new JsonResponse(['success' => FALSE, 'message' => $this->t('No image data provided')], 400);
    }

    // Process and save the image
    $success = $this->imageProcessor->saveEditedImage($media, $imageData);

    if ($success) {
      return new JsonResponse([
        'success' => TRUE,
        'message' => $this->t('Image saved successfully'),
        'redirect' => $media->toUrl()->toString(),
      ]);
    }

    return new JsonResponse([
      'success' => FALSE,
      'message' => $this->t('Failed to save image'),
    ], 500);
  }

}