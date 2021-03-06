<?php

namespace DrupalModuleChecker;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckModulesCommand extends Command {

  /**
   * @var string
   *
   * A target site.
   */
  protected $site;

  /**
   * {@inheritdoc}
   */
  protected function configure() {

    $this
      ->setName('check_modules')
      ->setDescription('Checks installed modules on external Drupal 8 site.')
      ->addArgument('site', InputArgument::REQUIRED, 'Drupal 8 site to be checked.')
      ->addOption(
        'limit',
        'l',
        InputOption::VALUE_OPTIONAL,
        'How many modules should be checked?',
        100
      );

  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {

    $io = new SymfonyStyle($input, $output);

    $this->site = $input->getArgument('site');
    if (!filter_var($this->site, FILTER_VALIDATE_URL)) {
      $io->error($this->site . ' is not valid URL');
    }

    // Normalize the URL.
    $this->site = rtrim($this->site, '/') . '/';

    $path = parse_url($this->site, PHP_URL_PATH) . 'core/install.php';

    $client = new Client(['base_uri' => $this->site]);
    try {
      $response = $client->get($path);
    }
    catch (ConnectException $exception) {
      $io->error('Could not connect to ' . $this->site);
      return 1;
    }
    catch (ClientException $exception) {
      $io->error('Could not open ' . $path . ' file.');
      return 1;
    }
    catch (RequestException $exception) {
      $io->error('Could not load ' . $this->site);
      return 1;
    }

    $body = (string) $response->getBody();

    // Find some untranslatable string to make sure the site can be checked.
    if (strpos($body, '<em>default.settings.php</em>') === FALSE) {
      $io->error('The site cannot be checked.');
      return 1;
    }

    $data_dir = __DIR__ . '/../data';

    // See http://modulecharts.org/chart-web.json.
    $modules = file($data_dir . '/modules.txt', FILE_IGNORE_NEW_LINES);

    $installed_modules = [];
    $checked_counter = 0;
    $installed_counter = 0;
    $limit = (int) $input->getOption('limit');

    $total_modules = min($limit, count($modules));
    $io->progressStart($total_modules);

    foreach ($modules as $module) {
      if ($checked_counter == $input->getOption('limit')) {
        break;
      }
      try {
        $response = $client->get($path . '?profile=' . $module);
        $io->progressAdvance();
        $checked_counter++;
        $body = (string) $response->getBody();

        if (self::getModuleStatus($module, $body)) {
          $installed_counter++;
          $url = 'https://www.drupal.org/project/' . $module;
          $installed_modules[] = [$installed_counter, $module, $url];
        }
      }
      catch (RequestException $exception) {
        $io->error('The site cannot be checked.');
        return 1;
      }

    }

    $io->progressFinish();

    $headers = ['#', 'Name', 'URL'];
    $io->table($headers, $installed_modules);
    $io->writeln(sprintf('Found modules: %d of %d.', count($installed_modules), $total_modules));
  }

  /**
   * Extracts module status from response body.
   *
   * @return bool
   *   True if the module is installed false otherwise.
   */
  protected static function getModuleStatus($module, &$body) {
    if (strpos($body, '<em>default.settings.php</em>') === FALSE) {
      throw new RuntimeException();
    }
    return strpos($body, 'The following module is missing from the file system: ' . $module) === FALSE;
  }

}
