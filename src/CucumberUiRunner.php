<?php

namespace Drupal\cucumber_ui;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Runs Cucumber test features and writes the Gherkin scripts they come from.
 *
 * The run tests screen and the create test screen share this, so a full run and
 * a single feature run behave the same way and report the same errors.
 */
class CucumberUiRunner {

  use StringTranslationTrait;

  /**
   * Constructs a CucumberUiRunner object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected FileSystemInterface $fileSystem,
    protected MessengerInterface $messenger,
  ) {
  }

  /**
   * Assembles the shell command that runs the configured test formatter.
   *
   * @param string $extra
   *   Extra arguments added to the command.
   *
   * @return string
   *   The command, or an empty string when the report directories are not
   *   usable. Problems are reported through the messenger.
   */
  public function buildCommand(string $extra = ''): string {
    $config = $this->configFactory->get('cucumber_ui.settings');
    $config_path = $config->get('config_path');
    $html_report_dir = $config->get('html_report_dir');
    $json_report_dir = $config->get('json_report_dir');
    $formatter = $config->get('html_report_format');

    $directory = $config->get('html_report') ? $html_report_dir : $config->get('log_report_dir');
    if (empty($directory)) {
      $this->messenger->addError($this->t('The report directory is not configured.'));
      return '';
    }
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY)) {
      $this->messenger->addError($this->t('The Output directory does not exist or is not writable.'));
      return '';
    }

    if ($formatter === 'html') {
      $format = ' --format html:' . $html_report_dir . '/index.html';
    }
    else {
      $format = ' --format json:' . $html_report_dir . '/index.json';
    }

    $command = 'cd ' . $config_path . '; yarn nightwatch' . ($extra === '' ? '' : ' ' . $extra) . $format;
    if ($formatter !== 'html') {
      $command .= '; node ' . $formatter . '.js';
    }

    if ($config->get('json_report') && !empty($json_report_dir) && $formatter === 'html') {
      if ($this->fileSystem->prepareDirectory($json_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
        $command .= ' --format json:' . $json_report_dir . '/index.json';
      }
      else {
        $this->messenger->addError($this->t('The JSON Output directory does not exist or is not writable.'));
      }
    }

    return $command . ';';
  }

  /**
   * Runs the configured Cucumber command.
   *
   * @param string $extra
   *   Extra arguments added to the command.
   */
  public function run(string $extra = ''): void {
    $command = $this->buildCommand($extra);
    if ($command !== '') {
      shell_exec($command);
    }
  }

  /**
   * Derives a feature file name from the first line of a Gherkin script.
   *
   * @param string $content
   *   The Gherkin script.
   *
   * @return string
   *   A file name without its extension.
   */
  public function featureName(string $content): string {
    $name = preg_split('#\r?\n#', ltrim($content), 2)[0];
    $name = str_replace(['Feature : ', 'Feature: '], '', $name);
    $name = preg_replace('#[^a-zA-Z0-9\- ]#', '', $name);
    $name = strtolower(str_replace(' ', '-', trim($name)));

    return $name !== '' ? $name : 'feature';
  }

  /**
   * The absolute path of a file inside the configured features directory.
   *
   * @param string $file_name
   *   The file name.
   *
   * @return string|null
   *   The path, or NULL when the features directory is not usable.
   */
  public function featurePath(string $file_name): ?string {
    $features_path = $this->configFactory->get('cucumber_ui.settings')->get('features_path');

    if (empty($features_path) || !$this->fileSystem->prepareDirectory($features_path, FileSystemInterface::CREATE_DIRECTORY)) {
      $this->messenger->addError($this->t('The Features directory does not exist or is not writable.'));
      return NULL;
    }

    return $features_path . '/' . $file_name;
  }

  /**
   * Writes a Gherkin script into the configured features directory.
   *
   * @param string $file_name
   *   The file name.
   * @param string $content
   *   The Gherkin script.
   *
   * @return string|null
   *   The written path, or NULL when the script could not be written.
   */
  public function writeFeature(string $file_name, string $content): ?string {
    $file = $this->featurePath($file_name);
    if ($file === NULL || file_put_contents($file, $content) === FALSE) {
      return NULL;
    }

    return $file;
  }

}
