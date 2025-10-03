<?php

namespace Filemanager\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableImportType extends Migration
{
  public function apply()
  {
    $this->Table('FMN_IMPORT_TYPE')
      ->id('id_fmn_import_type')
      ->string('ds_tag', 20)->Index('TAG', DbVocab::IDX_UNIQUE)->onColumn('ds_tag')
      ->string('ds_title', 50)
      ->string('ds_servicepath', 255)
      ->string('ds_servicemethod', 50);
  }
}
