<?php

namespace Filemanager\Services;

use SplitPHP\Database\Dao;
use SplitPHP\Service;
use SplitPHP\Exceptions\BadRequest;
use Throwable;

class Import extends Service
{
  const TABLE = "FMN_FILE_IMPORT";

  /**
   * List all records of importations, filtered by $params
   * @param   array  $params 
   * @return  array 
   */
  public function list($params = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->find("imports/read");
  }

  /**
   * Find the first record of importations, filtered by $params
   * @param   array  $params 
   * @return  object 
   */
  public function get($params = [])
  {
    return $this->getDao(self::TABLE)
      ->bindParams($params)
      ->first("imports/read");
  }

  /**
   * Create a record importation
   * @param   array  $data 
   * @return  object 
   */
  public function create($data)
  {
    // Removes forbidden fields from $data
    $data = $this->getService('utils/misc')->dataBlackList($data, [
      'id_fmn_file_import',
      'ds_key',
      'dt_created',
      'id_iam_user_created',
    ]);

    // Set refs
    $loggedUser = null;
    if ($this->getService('modcontrol/control')->moduleExists('iam'))
      $loggedUser = $this->getService('iam/session')->getLoggedUser();

    // Validation
    if (!isset($_FILES['file']) && !isset($data['id_fmn_file']))
      throw new BadRequest("Arquivo não enviado.");

    if (!in_array($data['ds_type_tag'], $this->getTypeTags()))
      throw new BadRequest("Tipo de importação inválido.");

    // Set default values
    $data['ds_key'] = 'imp-' . uniqid();
    $data['id_iam_user_created'] = empty($loggedUser) ? null : $loggedUser->id_iam_user;

    $file = $this->getService('filemanager/file')
      ->create($_FILES['file']['name'], $_FILES['file']['tmp_name'], 'Y');

    if (!empty($_FILES['file'])) {
      $file = $this->getService('filemanager/file')
        ->create($_FILES['file']['name'], $_FILES['file']['tmp_name'], 'Y');

      if (!empty($file)) {
        $data['id_fmn_file'] = $file->id_fmn_file;
      }
    }

    return $this->getDao(self::TABLE)->insert($data);
  }

  /**
   * Cancel importations, filtered by $params
   * @param   array  $params 
   * @return  int    Number of affected rows
   */
  public function cancel($params)
  {
    // Set refs
    $target = $this->list($params);
    $loggedUser = null;
    if ($this->getService('modcontrol/control')->moduleExists('iam'))
      $loggedUser = $this->getService('iam/session')->getLoggedUser();

    // Set default values:
    $data = [];
    $data['do_status'] = 'C'; // C=Cancelled
    $data['id_iam_user_updated'] = empty($loggedUser) ? null : $loggedUser->id_iam_user;
    $data['dt_updated'] = date("Y-m-d H:i:s");

    $count = 0;
    foreach ($target as $item) {
      $count += $this->getDao(self::TABLE)
        ->filter('id_fmn_file_import', $item->id_fmn_file_import)
        ->update($data);
    }

    return $count;
  }

  /**
   * Remove importations, filtered by $params
   * @param   array  $params 
   * @return  int    Number of affected rows
   */
  public function remove($params)
  {
    $records = $this->list($params);

    $count = 0;
    foreach ($records as $item)
      $count += $this->getService('filemanager/file')->remove(['id_fmn_file' => $item->id_fmn_file]);

    return $count;
  }

  /**
   * Process all pending importations
   * @return  void
   */
  public function import()
  {
    $target = $this->list(['do_status' => 'P']); // P=pending

    foreach ($target as $import) {
      $this->getDao(self::TABLE)
        ->filter('id_fmn_file_import', $import->id_fmn_file_import)
        ->update(['do_status' => 'R']); // R=Running

      Dao::flush();

      try {
        $this->getService($import->ds_servicepath)->{$import->ds_servicemethod}($import);
        $this->getDao(self::TABLE)
          ->filter('id_fmn_file_import', $import->id_fmn_file_import)
          ->update(['do_status' => 'D']); // D=Done
      } catch (Throwable $exc) {
        $this->getDao(self::TABLE)
          ->filter('id_fmn_file_import', $import->id_fmn_file_import)
          ->update([
            'do_status' => 'F', // F=Failed
            'ds_failreason' => $exc->getMessage()
          ]);
      }
      Dao::flush();
    }
  }

  /**
   * Get all valid importation types
   * @return  array
   */
  public function getTypes()
  {
    return $this->getDao('FMN_IMPORT_TYPE')
      ->find();
  }

  /**
   * Get all valid importation type tags
   * @return  array
   */
  public function getTypeTags()
  {
    return array_map(fn($t) => $t->ds_tag, $this->getTypes());
  }
}
