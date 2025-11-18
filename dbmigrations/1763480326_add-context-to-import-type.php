<?php

namespace Filemanager\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class AddContextToImportType extends Migration
{
  public function apply()
  {
    $this->Table('FMN_IMPORT_TYPE')
      ->string('ds_context', 50)
      ->nullable()->setDefaultValue(null);
  }
}
