<?php

namespace Filemanager\Services;

use SplitPHP\Service;
use SplitPHP\Helpers;

class File extends Service
{
  const TABLE = "FMN_FILE";

  /** Maximum file size (in bytes) before image optimization kicks in. */
  private const IMAGE_SIZE_LIMIT = 1_048_576; // 1 MB

  /** Maximum pixel dimension (longest side) when resizing images. */
  private const IMAGE_MAX_DIMENSION = 1920;

  /** MIME types eligible for GD-based optimization. */
  private const OPTIMIZABLE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
  ];

  /**
   * List all records in FMN_FILE
   * @param   array  $params 
   * @return  object 
   */
  public function list($params = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->find();
  }


  /**
   * List first record in FMN_FILE, filtered by $params
   * @param   array  $params 
   * @return  object 
   */
  public function get($params = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->first();
  }


  /**
   * Create a record in FMN_FILE
   * @param   string  $name     The name of the file
   * @param   string  $filepath The path to the file
   * @param   string  $external Optional. Pass 'Y' to save the content in S3.
   * @return  object 
   */
  public function add(string $name, string $filepath, string $external = 'N')
  {
    // Ensures the name is valid
    $name = $this->getService('utils/misc')->stringToSlug($name);

    $data = [];

    // Set default values
    $data['ds_key'] = "fle-" . uniqid();
    $data['ds_filename'] = $name;
    $data['do_external_storage'] = $external;
    $data['ds_content_type'] = $this->findMimeType($filepath);

    // Optimize images that exceed the size limit:
    $filepath = $this->optimizeImageIfNeeded($filepath, $data['ds_content_type']);

    // Use S3 Service
    if ($external === 'Y') {
      $data['ds_url'] = $this->getService('filemanager/s3')
        ->putObject($filepath, "{$data['ds_key']}_{$data['ds_filename']}", $data['ds_content_type']);
    } else {
      $data['bl_file'] = file_get_contents($filepath);
    }

    return $this->getDao(self::TABLE)->insert($data);
  }


  /**
   * Delete a record in FMN_FILE using filters specified in the array $params
   * @param   array  $data 
   * @return  object 
   */
  public function remove($params)
  {
    $data = $this->list($params);
    $affectedRows = 0;

    foreach ($data as $item) {
      if ($item->do_external_storage === 'Y') {
        $found = $this->getService('filemanager/s3')->deleteObject("{$item->ds_key}_{$item->ds_filename}");
        if (!$found) {
          Helpers::Log()->common("filemanager_s3", "file id {$item->id_fmn_file} could not be deleted");
          continue;
        }
      }
      $affectedRows += $this->getDao(self::TABLE)->bindParams(['ds_key' => $item->ds_key])->delete();
    }

    return $affectedRows;
  }

  // ─── Image Optimization ──────────────────────────────────────

  /**
   * Optimizes an image file if it exceeds the size limit.
   * Non-image files and images already within the limit pass through untouched.
   *
   * @return string The (possibly new) file path to use for upload.
   */
  private function optimizeImageIfNeeded(string $filepath, string $mimeType): string
  {
    if (!$this->isOptimizableImage($filepath, $mimeType)) {
      return $filepath;
    }

    try {
      return $this->optimizeImage($filepath, $mimeType);
    } catch (\Throwable $e) {
      error_log('[FileManager] Image optimization failed, uploading original: ' . $e->getMessage());
      return $filepath;
    }
  }

  /**
   * Checks whether a file is an image that exceeds the size limit
   * and can be processed by GD.
   */
  private function isOptimizableImage(string $filepath, string $mimeType): bool
  {
    if (!in_array($mimeType, self::OPTIMIZABLE_TYPES, true)) {
      return false;
    }

    return filesize($filepath) > self::IMAGE_SIZE_LIMIT;
  }

  /**
   * Loads, resizes, and recompresses the image until it fits within the limit.
   * Returns the path to the optimized temp file.
   */
  private function optimizeImage(string $filepath, string $mimeType): string
  {
    $image = $this->loadImage($filepath, $mimeType);
    $image = $this->scaleDown($image);

    $quality = 85;
    $minQuality = 40;
    $step = 10;
    $tmpPath = tempnam(sys_get_temp_dir(), 'fmn_opt_');

    while ($quality >= $minQuality) {
      $this->saveImage($image, $tmpPath, $mimeType, $quality);

      if (filesize($tmpPath) <= self::IMAGE_SIZE_LIMIT) {
        imagedestroy($image);
        return $tmpPath;
      }

      $quality -= $step;
    }

    // If still over limit after min quality, accept the last result:
    imagedestroy($image);
    return $tmpPath;
  }

  /**
   * Loads a file into a GD image resource.
   */
  private function loadImage(string $filepath, string $mimeType): \GdImage
  {
    return match ($mimeType) {
      'image/jpeg' => imagecreatefromjpeg($filepath),
      'image/png'  => imagecreatefrompng($filepath),
      'image/webp' => imagecreatefromwebp($filepath),
      default      => throw new \RuntimeException("Unsupported image type: $mimeType"),
    };
  }

  /**
   * Saves a GD image resource to a file with the given quality.
   */
  private function saveImage(\GdImage $image, string $path, string $mimeType, int $quality): void
  {
    match ($mimeType) {
      'image/jpeg' => imagejpeg($image, $path, $quality),
      'image/png'  => imagepng($image, $path, (int) round((100 - $quality) / 11.1)),
      'image/webp' => imagewebp($image, $path, $quality),
      default      => throw new \RuntimeException("Unsupported image type: $mimeType"),
    };
  }

  /**
   * Scales down an image if its longest side exceeds IMAGE_MAX_DIMENSION.
   * Returns the (possibly resized) image resource.
   */
  private function scaleDown(\GdImage $image): \GdImage
  {
    $width = imagesx($image);
    $height = imagesy($image);
    $longest = max($width, $height);

    if ($longest <= self::IMAGE_MAX_DIMENSION) {
      return $image;
    }

    $ratio = self::IMAGE_MAX_DIMENSION / $longest;
    $newWidth = (int) round($width * $ratio);
    $newHeight = (int) round($height * $ratio);

    $resized = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG/WebP:
    imagealphablending($resized, false);
    imagesavealpha($resized, true);

    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($image);

    return $resized;
  }

  // ─── Helpers ─────────────────────────────────────────────────

  private function findMimeType($filepath)
  {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filepath);
    finfo_close($finfo);

    return $mimeType;
  }
}

