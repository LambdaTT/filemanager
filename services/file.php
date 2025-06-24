<?php

namespace Filemanager\Services;

use SplitPHP\Service;
use SplitPHP\Helpers;

class File extends Service
{
  const TABLE = "FMN_FILE";

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
          Helpers::Log()->add("filemanager_s3", "file id {$item->id_fmn_file} could not be deleted");
          continue;
        }
      }
      $affectedRows += $this->getDao(self::TABLE)->bindParams(['ds_key' => $item->ds_key])->delete();
    }

    return $affectedRows;
  }

  private function findMimeType($filepath)
  {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filepath);
    finfo_close($finfo);

    return $mimeType;
  }
}
