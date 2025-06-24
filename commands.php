<?php

namespace Filemanager\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Commands extends Cli
{
  public function init()
  {
    $this->addCommand('files:list', function ($args) {
      // Extract and normalize our options
      $limit   = isset($args['--limit']) ? (int)$args['--limit'] : 10;
      $sortBy  = $args['--sort-by']         ?? null;
      $sortDir = $args['--sort-direction']  ?? 'ASC';
      unset($args['--limit'], $args['--sort-by'], $args['--sort-direction']);

      $page = isset($args['--page']) ? (int)$args['--page'] : 1;
      unset($args['--page']);

      // --- <== HERE: open STDIN in BLOCKING mode (no stream_set_blocking) ===>
      $stdin = fopen('php://stdin', 'r');
      // on *nix, disable line buffering & echo
      if (DIRECTORY_SEPARATOR !== '\\') {
        system('stty -icanon -echo');
      }

      $exit = false;
      while (! $exit) {
        // Clear screen + move cursor home
        if (DIRECTORY_SEPARATOR === '\\') {
          system('cls');
        } else {
          echo "\033[2J\033[H";
        }

        // Header & hints
        Utils::printLn($this->getService('utils/clihelper')->ansi("Welcome to the BPM Workflow List Command!\n", 'color: cyan; font-weight: bold'));
        Utils::printLn("HINTS:");
        Utils::printLn("  • --limit={$limit}   (items/page)");
        Utils::printLn("  • --sort-by={$sortBy}   --sort-direction={$sortDir}");
        if (DIRECTORY_SEPARATOR === '\\') {
          Utils::printLn("  • Press 'n' = next page, 'p' = previous page, 'q' = quit");
        } else {
          Utils::printLn("  • ←/→ arrows to navigate pages, 'q' to quit");
        }
        Utils::printLn("  • Press 'ctrl+c' to exit at any time");
        Utils::printLn();

        // Fetch & render
        $params = array_merge($args, [
          '$limit' => $limit,
          '$limit_multiplier' => 1, // No multiplier for pagination
          '$page'  => $page,
        ]);
        if ($sortBy) {
          $params['$sort_by']        = $sortBy;
          $params['$sort_direction'] = $sortDir;
        }

        $rows = $this->getService('filemanager/file')->list($params);

        if (empty($rows)) {
          Utils::printLn("  >> No files found on page {$page}.");
        } else {
          Utils::printLn(" Page {$page} — showing " . count($rows) . " items");
          Utils::printLn(str_repeat('─', 60));
          $this->getService('utils/clihelper')->table($rows, [
            'id_fmn_file'              => 'ID',
            'dt_created'               => 'Created At',
            'ds_filename'              => 'Filename',
            'do_external_storage'      => 'External Storage?',
            'ds_tag'                   => 'Tag',
            'ds_url'                   => 'URL',
            'ds_content_type'          => 'Content Type',
          ]);
        }

        // --- <== HERE: wait for exactly one keypress, blocking until you press ===>
        $c = fgetc($stdin);
        if (DIRECTORY_SEPARATOR === '\\') {
          $input = strtolower($c);
        } else {
          if ($c === "\033") {             // arrow keys start with ESC
            $input = $c . fgetc($stdin) . fgetc($stdin);
          } else {
            $input = $c;
          }
        }

        // Handle navigation
        if (DIRECTORY_SEPARATOR === '\\') {
          switch ($input) {
            case 'n':
              $page++;
              break;
            case 'p':
              $page = max(1, $page - 1);
              break;
            case 'q':
              $exit = true;
              break;
          }
        } else {
          switch ($input) {
            case "\033[C": // →
              $page++;
              break;
            case "\033[D": // ←
              $page = max(1, $page - 1);
              break;
            case 'q':
              $exit = true;
              break;
          }
        }
      }

      // Restore terminal settings on *nix
      if (DIRECTORY_SEPARATOR !== '\\') {
        system('stty sane');
      }

      // Cleanup
      fclose($stdin);
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
          'usage' => 'filamanager:files:list [--limit=<n>] [--sort-by=<field>] [--sort-direction=<dir>] [--page=<n>]',
          'desc'  => 'Page through existing files.',
          'flags' => [
            '--limit=<n>'          => 'Items per page (default 10)',
            '--sort-by=<field>'    => 'Field to sort by',
            '--sort-direction=<d>' => 'ASC or DESC (default ASC)',
            '--page=<n>'           => 'Page number (default 1)',
          ],
        ],
        'files:create' => [
          'usage' => 'filamanager:files:create',
          'desc'  => 'Interactively create a new file.',
        ],
        'files:remove' => [
          'usage' => 'filamanager:files:remove',
          'desc'  => 'Delete a file by its ID.',
        ],
        'help'             => [
          'usage' => 'filamanager:help',
          'desc'  => 'Show this help screen.',
        ],
      ];

      // 2) Summary table
      Utils::printLn($helper->ansi("\nAvailable commands:\n", 'color: cyan; text-decoration: underline'));

      $rows = [
        [
          'cmd'  => 'filamanager:files:list',
          'desc' => 'Page through existing files',
          'opts' => '--limit, --sort-by, --sort-direction, --page',
        ],
        [
          'cmd'  => 'filamanager:files:create',
          'desc' => 'Interactively create a new file',
          'opts' => '(no flags)',
        ],
        [
          'cmd'  => 'filamanager:files:remove',
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
