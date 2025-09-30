<?php

namespace Filemanager\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateTableFileImport extends Migration
{
  public function apply()
  {
    $this->Table('FMN_FILE_IMPORT')
      ->id('id_fmn_file_import')
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')
      ->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL)
      ->string('ds_type_tag', 5)
      ->int('id_fmn_file')
      ->Foreign('id_fmn_file')->references('id_fmn_file')->atTable('FMN_FILE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->text('tx_extradata')->nullable()->setDefaultValue(null)
      ->string('do_status', 1)->setDefaultValue('P'); // P=pending, R=Running, D=Done, F=Failed, C=Cancelled
  }
}
