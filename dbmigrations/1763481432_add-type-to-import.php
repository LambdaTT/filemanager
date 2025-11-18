<?php

namespace Filemanager\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class AddTypeToImport extends Migration
{
  public function apply()
  {
    $this->Table('FMN_FILE_IMPORT')
      ->int('id_fmn_import_type')->nullable()->setDefaultValue(null)
      ->Foreign('id_fmn_import_type')->references('id_fmn_import_type')->atTable('FMN_IMPORT_TYPE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);
  }
}
