<?php

namespace Filemanager\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Commands extends Cli
{
  public function init(): void
  {
    $this->addCommand('files:list', function ($args) {
      $getRows = function ($params) {
        return $this->getService('filemanager/file')->list($params);
      };

      $columns = [
        'id_fmn_file'         => 'ID',
        'dt_created'          => 'Created At',
        'ds_filename'         => 'Filename',
        'do_external_storage' => 'External Storage?',
        'ds_tag'              => 'Tag',
        'ds_url'              => 'URL',
        'ds_content_type'     => 'Content Type',
      ];

      $this->getService('utils/misc')->printDataTable("Files List", $getRows, $columns, $args);
    });

    $this->addCommand('files:add', function ($args) {
      if (in_array('--external-storage', $args)) {
        $external = 'Y';
        unset($args['--external-storage']);
      } else {
        $external = 'N';
      }

      Utils::printLn("Welcome to the Filemanager Add Command!");
      Utils::printLn("This command will help you add a new file.");
      Utils::printLn();
      Utils::printLn(" >> Please follow the prompts to define your file informations.");
      Utils::printLn();
      Utils::printLn("  >> Add File:");
      Utils::printLn("------------------------------------------------------");

      $file = $this->getService('utils/clihelper')->inputForm([
        'ds_filename' => [
          'label' => 'File Name',
          'required' => true,
          'length' => 255,
        ],
        'filepath' => [
          'label' => 'Absolute File Path',
          'required' => true,
          'length' => 255,
        ],
      ]);

      $record = $this->getService('filemanager/file')->add(
        $file->ds_filename,
        $file->filepath,
        $external
      );

      Utils::printLn("  >> File added successfully!");
      foreach ($record as $key => $value) {
        Utils::printLn("    -> {$key}: {$value}");
      }
    });

    $this->addCommand('files:remove', function () {
      Utils::printLn("Welcome to the File Removal Command!");
      Utils::printLn();
      $fileId = readline("  >> Please, enter the File ID you want to remove: ");

      $this->getService('filemanager/file')->remove([
        'id_fmn_file' => $fileId,
      ]);
      Utils::printLn("  >> File with ID {$fileId} removed successfully!");
    });

    $this->addCommand('help', function () {
      /** @var \Utils\Services\CliHelper $helper */
      $helper = $this->getService('utils/clihelper');
      Utils::printLn($helper->ansi(strtoupper("Welcome to the Filemanager Help Center!"), 'color: magenta; font-weight: bold'));

      // 1) Define metadata for each command
      $commands = [
        'files:list'   => [
          'usage' => 'filemanager:files:list [--limit=<n>] [--sort-by=<field>] [--sort-direction=<dir>] [--page=<n>]',
          'desc'  => 'Page through existing files.',
          'flags' => [
            '--limit=<n>'          => 'Items per page (default 10)',
            '--sort-by=<field>'    => 'Field to sort by',
            '--sort-direction=<d>' => 'ASC or DESC (default ASC)',
            '--page=<n>'           => 'Page number (default 1)',
          ],
        ],
        'files:create' => [
          'usage' => 'filemanager:files:create',
          'desc'  => 'Interactively create a new file.',
        ],
        'files:remove' => [
          'usage' => 'filemanager:files:remove',
          'desc'  => 'Delete a file by its ID.',
        ],
        'help'             => [
          'usage' => 'filemanager:help',
          'desc'  => 'Show this help screen.',
        ],
      ];

      // 2) Summary table
      Utils::printLn($helper->ansi("\nAvailable commands:\n", 'color: cyan; text-decoration: underline'));

      $rows = [
        [
          'cmd'  => 'filemanager:files:list',
          'desc' => 'Page through existing files',
          'opts' => '--limit, --sort-by, --sort-direction, --page',
        ],
        [
          'cmd'  => 'filemanager:files:create',
          'desc' => 'Interactively create a new file',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'filemanager:files:remove',
          'desc' => 'Delete a file by ID',
          'opts' => '(no flags)',
        ],
      ];

      $helper->table($rows, [
        'cmd'  => 'Command',
        'desc' => 'Description',
        'opts' => 'Options',
      ]);

      // 3) Detailed usage lists
      foreach ($commands as $cmd => $meta) {
        Utils::printLn($helper->ansi("\n{$cmd}", 'color: yellow; font-weight: bold'));
        Utils::printLn("  Usage:   {$meta['usage']}");
        Utils::printLn("  Purpose: {$meta['desc']}");

        if (!empty($meta['flags'])) {
          Utils::printLn("  Options:");
          $flagLines = [];
          foreach ($meta['flags'] as $flag => $explain) {
            $flagLines[] = "{$flag}  — {$explain}";
          }
          $helper->listItems($flagLines, false, '    •');
        }
      }

      Utils::printLn(''); // trailing newline
    });
  }
}
