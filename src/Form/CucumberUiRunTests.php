<?php

namespace Drupal\cucumber_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Cucumber UI Run Tests class.
 */
class CucumberUiRunTests extends FormBase
{

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The tempstore object.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStore;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Constructs a CucumberUiNew object.
   *
   * @param \Drupal\Core\Config\ConfigFactory              $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface      $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileSystemInterface          $file_system
   *   The file system service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Symfony\Component\HttpFoundation\Request      $current_request
   *   The current request.
   */
  public function __construct(ConfigFactory $config_factory, MessengerInterface $messenger, FileSystemInterface $file_system, PrivateTempStoreFactory $temp_store_factory, Request $current_request)
  {
      $this->configFactory = $config_factory;
      $this->messenger = $messenger;
      $this->fileSystem = $file_system;
      $this->tempStore = $temp_store_factory;
      $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
      return new static(
          $container->get('config.factory'),
          $container->get('messenger'),
          $container->get('file_system'),
          $container->get('tempstore.private'),
          $container->get('request_stack')->getCurrentRequest()
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
      return 'cucumber_ui_run_tests';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {

      $active_theme = \Drupal::theme()->getActiveTheme();
      $base_themes = (array) $active_theme->getBaseThemeExtensions();

      if ($active_theme->getName() === 'gin'|| array_key_exists('gin', $base_themes)) {
          $form['#attached']['library'][] = 'cucumber_ui/style.gin';
      }
      elseif ($active_theme->getName() === 'claro'|| array_key_exists('claro', $base_themes)) {
          $form['#attached']['library'][] = 'cucumber_ui/style.claro';
      }

      $form['#attached']['library'][] = 'cucumber_ui/run-tests-scripts';

      $config = $this->configFactory->getEditable('cucumber_ui.settings');

      $html_report = $config->get('html_report');
      $html_report_dir = $config->get('html_report_dir');
      $log_report_dir = $config->get('log_report_dir');

      $beaht_ui_tempstore_collection = $this->tempStore->get('cucumber_ui');
      $pid = $beaht_ui_tempstore_collection->get('cucumber_ui_pid');

      $label = $this->t('Not running');
      $class = '';

      if ($pid && posix_kill(intval($pid), 0)) {
          $label = $this->t("Process:") . $pid . ' ' . $this->t('Running <small><a href="#" id="cucumber-ui-kill">(kill)</a></small>');
          $class = 'running';
      }

      $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run cucumber tests'),
      ];

      $form['cucumber_ui_status'] = [
      '#type' => 'markup',
      '#markup' => '<p id="cucumber-ui-status" class="' . $class . '">' . $this->t('Status:') . ' <span>' . $label . '</span></p>',
      ];

      if ($html_report) {

          if (isset($html_report_dir) && $html_report_dir != '') {

              $html_report_output = $html_report_dir . '/index.html';
              if ($html_report_output && file_exists($html_report_output)) {
  
                  $report_url = new Url('cucumber_ui.report');
                  $form['cucumber_ui_output'] = [
                  '#title' => $this->t('Tests output'),
                  '#type' => 'markup',
                  '#markup' => '<div id="cucumber-ui-output"><iframe id="cucumber-ui-output-iframe" src="' . $this->currentRequest->getSchemeAndHttpHost() . $report_url->toString() . '" width="100%" height="100%"></iframe></div>',
                  ];
              }
              else {
                  $form['cucumber_ui_output'] = [
                  '#title' => $this->t('Tests output'),
                  '#type' => 'markup',
                  '#markup' => '<div id="cucumber-ui-output">' . $this->t('No HTML report yet') . '</div>',
                  ];
              }
          }
          else {
              $this->messenger->addError($this->t('The HTML report directory is not configured.'));
          }
      }
      else {

          if (isset($log_report_dir) && $log_report_dir != '') {

              $log_report_output = $log_report_dir . '/cucumber-ui-test.log';
              if ($log_report_output && file_exists($log_report_output)) {
                  $log_report_output_content = nl2br(htmlentities(file_get_contents($log_report_output)));
                  $form['cucumber_ui_output'] = [
                  '#title' => $this->t('Tests output'),
                  '#type' => 'markup',
                  '#markup' => '<div id="cucumber-ui-output">' . $log_report_output_content . '</div>',
                  ];
              }
              else {
                  $form['cucumber_ui_output'] = [
                  '#title' => $this->t('Tests output'),
                  '#type' => 'markup',
                  '#markup' => '<div id="cucumber-ui-output">' . $this->t('No Log report yet') . '</div>',
                  ];
              }
          }
          else {
              $this->messenger->addError($this->t('The Console Log report directory is not configured.'));
          }
      }

      return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config = $this->configFactory->getEditable('cucumber_ui.settings');
    $bin_path = '';
    $config_path = $config->get('config_path');
    $config_file = '';
    $features_path = $config->get('features_path');

    $html_report = $config->get('html_report');
    $html_report_dir = $config->get('html_report_dir');

    $html_report_formatter = $config->get('html_report_format');
    if ($html_report_formatter == "html") {
      $html_report_format = " --format $html_report_formatter:" . $html_report_dir . "/index.html";
    }
    else {
      $html_report_format = " --format json:" . $html_report_dir . "/index.json";
    }
    $log_report_dir = $config->get('log_report_dir');
    $save_user_testing_features = $config->get('save_user_testing_features');
    $editing_mode = $config->get('editing_mode');

    $command = '';

    if ($html_report) {

      if (isset($html_report_dir) && $html_report_dir != '') {

        if ($this->fileSystem->prepareDirectory($html_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
          $command = "cd $config_path; yarn nightwatch $html_report_format";
          if ($html_report_formatter != "html") {
            $command .= "; node ".$html_report_formatter.".js";
          }
        }
        else {
          $this->messenger->addError($this->t('The HTML Output directory does not exists or is not writable.'));
        }
      }
      else {
        $this->messenger->addError($this->t('HTML report directory and file is not configured.'));
      }

    }
    else {

      if (isset($log_report_dir) && $log_report_dir != '') {

        if ($this->fileSystem->prepareDirectory($log_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
          $log_report_output_file = $log_report_dir . '/cucumber-ui-test.log';
          $command = "cd $config_path; yarn nightwatch $html_report_format";
          if ($html_report_formatter != "html") {
            $command .= "; node ".$html_report_formatter.".js";
          }
        }
        else {
          $this->messenger->addError($this->t('The Log Output directory does not exists or is not writable.'));
        }
      }
      else {
        $this->messenger->addError($this->t('The Log directory and file is not configured.'));
      }
    }

    // JSON report format
    $json_report = $config->get('json_report');
    $json_report_dir = $config->get('json_report_dir');

    if ($json_report) {

      if (isset($json_report_dir) && $json_report_dir != '') {

        if ($this->fileSystem->prepareDirectory($json_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
          if ($html_report_formatter == "html") {
            $command .= " --format json:" . $json_report_dir . "/index.json";
          }
        }
        else {
          $this->messenger->addError($this->t('The JSON Output directory does not exists or is not writable.'));
        }
      }
      else {
        $this->messenger->addError($this->t('HTML report directory and file is not configured.'));
      }
    }

    $command .= ';';
    $output = shell_exec($command);

    if (isset($output)) {
      $report_html_file_name_and_path = $html_report_dir . '/index.html';

      $report_html_handle = fopen($report_html_file_name_and_path, 'r');
      
      $report_html = fread($report_html_handle, filesize($report_html_file_name_and_path));
      
      if (isset($report_html)) {
        fclose($report_html_handle);
      }
    }

    $report_url = new Url('cucumber_ui.report');

    $form['cucumber_ui_output'] = [
      '#title' => $this->t('Tests output'),
      '#type' => 'markup',
      '#markup' => Markup::create('<div id="cucumber-ui-output"><iframe id="cucumber-ui-output-iframe"  src="' . $this->currentRequest->getSchemeAndHttpHost() . $report_url->toString() . '" width="100%" height="100%"></iframe></div>'),
      ];

    return $form['cucumber_ui_output'];
  }

  /**
   * Validate Form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
      parent::validateForm($form, $form_state);
  }
}
