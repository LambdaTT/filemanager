<?php

namespace Filemanager\Migrations;

use SplitPHP\DbMigrations\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableFile extends Migration{
  public function apply(){
     $this->Table('FMN_FILE', 'File')
      ->id('id_fmn_file')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->string('ds_filename', 255)
      ->string('do_external_storage', 1)->setDefaultValue('N')
      ->string('ds_url', 255)->nullable()->setDefaultValue(null)
      ->blob('bl_file')->nullable()->setDefaultValue(null)
      ->string('ds_content_type', 100)->nullable()->setDefaultValue(null)
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key');

  }
}