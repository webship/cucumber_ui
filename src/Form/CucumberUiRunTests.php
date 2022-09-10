<?php

namespace Drupal\cucumber_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
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
class CucumberUiRunTests extends FormBase {

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
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   */
  public function __construct(ConfigFactory $config_factory, MessengerInterface $messenger, FileSystemInterface $file_system, PrivateTempStoreFactory $temp_store_factory, Request $current_request) {
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->fileSystem = $file_system;
    $this->tempStore = $temp_store_factory;
    $this->currentRequest = $current_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
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
  public function getFormId() {
    return 'cucumber_ui_run_tests';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'cucumber_ui/style';
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

        $log_report_output = $log_report_dir . '/bethat-ui-test.log';
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
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Validate Form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $config = $this->configFactory->getEditable('cucumber_ui.settings');
    $bin_path = $config->get('bin_path');
    $config_path = $config->get('config_path');
    $config_file = $config->get('config_file');

    $features_path = $config->get('features_path');

    $html_report = $config->get('html_report');
    $html_report_dir = $config->get('html_report_dir');
    $log_report_dir = $config->get('log_report_dir');

    $beaht_ui_tempstore_collection = $this->tempStore->get('cucumber_ui');
    $pid = $beaht_ui_tempstore_collection->get('cucumber_ui_pid');

    $command = '';

    if ($pid && posix_kill(intval($pid), 0)) {
      $form_state->setErrorByName('submit_button', $this->t('Tests are already running!'));
    }
    else {

      $command = '';
      if ($html_report) {

        if (isset($html_report_dir) && $html_report_dir != '') {
          if ($this->fileSystem->prepareDirectory($html_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
            $command = "cd $config_path;$bin_path --config=$config_file $features_path --format pretty --out std --format html --out $html_report_dir";
          }
          else {
            $form_state->setErrorByName('submit_button', $this->t('The HTML Output directory does not exists or is not writable.'));
          }
        }
        else {
          $form_state->setErrorByName('submit_button', $this->t('HTML report directory and file is not configured.'));
        }
      }
      else {

        if (isset($log_report_dir) && $log_report_dir != '') {

          if ($this->fileSystem->prepareDirectory($log_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
            $log_report_output_file = $log_report_dir . "/bethat-ui-test.log";
            $command = "cd $config_path;$bin_path --config=$config_file $features_path --format pretty --out std > $log_report_output_file&";
          }
          else {
            $form_state->setErrorByName('submit_button', $this->t('The Log Output directory does not exists or is not writable.'));
          }
        }
        else {
          $form_state->setErrorByName('submit_button', $this->t('The Log directory and file is not configured.'));
        }
      }

      $process = new Process($command);
      $process->enableOutput();

      try {
        $process->start();
        $new_pid = $process->getPid() + 1;
        $this->messenger->addMessage($this->t("Started running tests using prcess ID: @pid", ["@pid" => $new_pid]));
        $beaht_ui_tempstore_collection->set('cucumber_ui_pid', $new_pid);

        if (!$process->isSuccessful()) {
          $this->messenger->addMessage($process->getErrorOutput());
        }
      }
      catch (ProcessFailedException $exception) {
        $form_state->setErrorByName('submit_button', $exception->getMessage());
      }
    }
  }

}
