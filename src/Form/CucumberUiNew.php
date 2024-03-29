<?php

namespace Drupal\cucumber_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Cucumber Ui New Scenarios/Feature class.
 */
class CucumberUiNew extends FormBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

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
   * Constructs a CucumberUiNew object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(ConfigFactory $config_factory, Request $current_request, MessengerInterface $messenger, FileSystemInterface $file_system) {
    $this->configFactory = $config_factory;
    $this->currentRequest = $current_request;
    $this->messenger = $messenger;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
          $container->get('config.factory'),
          $container->get('request_stack')->getCurrentRequest(),
          $container->get('messenger'),
          $container->get('file_system')
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cucumber_ui_new_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $active_theme = \Drupal::theme()->getActiveTheme();
    $base_themes = (array) $active_theme->getBaseThemeExtensions();

    if ($active_theme->getName() === 'gin'|| array_key_exists('gin', $base_themes)) {
      $form['#attached']['library'][] = 'cucumber_ui/style.gin';
    }
    elseif ($active_theme->getName() === 'claro'|| array_key_exists('claro', $base_themes)) {
      $form['#attached']['library'][] = 'cucumber_ui/style.claro';
    }

    $form['#attached']['library'][] = 'cucumber_ui/new-test-scripts';

    $config = $this->configFactory->getEditable('cucumber_ui.settings');

    $editing_mode = $config->get('editing_mode');
    if ($editing_mode == 'free_text') {

      $form['cucumber_ui_new_feature'] = [
        '#type' => 'markup',
        '#markup' => '<div class="layout-row clearfix">'
        . '  <div class="layout-column layout-column--half">'
        . '    <div id="cucumber-ui-new-scenario" class="panel">'
        . '      <h3 class="panel__title">' . $this->t('New Feature') . '</h3>'
        . '      <div class="panel__content">',
        ];

      $cucumber_ui_steps_link = new Url('cucumber_ui.cucumber_dl');
      $form['cucumber_ui_new_feature']['cucumber_ui_steps_link'] = [
        '#type' => 'markup',
        '#markup' => '<a class="button use-ajax"
              data-dialog-options="{&quot;width&quot;:500}" 
              data-dialog-renderer="off_canvas" 
              data-dialog-type="dialog"
              href="' . $this->currentRequest->getSchemeAndHttpHost() . $cucumber_ui_steps_link->toString() . '" >' . $this->t('Check available steps') . '</a>',
        ];

      $cucumber_ui_steps_link_with_info = new Url('cucumber_ui.cucumber_di');
      $form['cucumber_ui_new_feature']['cucumber_ui_steps_link_with_info'] = [
        '#type' => 'markup',
        '#markup' => '<a class="button use-ajax"
              data-dialog-options="{&quot;width&quot;:500}" 
              data-dialog-renderer="off_canvas" 
              data-dialog-type="dialog"
              href="' . $this->currentRequest->getSchemeAndHttpHost() . $cucumber_ui_steps_link_with_info->toString() . '" >' . $this->t('Full steps with info') . '</a>',
        ];

      $form['cucumber_ui_new_feature']['free_text'] = [
        '#type' => 'textarea',
        '#rows' => 30,
        '#resizable' => TRUE,
        '#attributes' => [
        'class' => ['free-text-ace-editor'],
        ],
        '#default_value' => $this->getFeature(),
        ];
      $form['cucumber_ui_new_feature']['free_text_ace_editor'] = [
        '#type' => 'markup',
        '#markup' => '<div id="free_text_ace_editor">' . $this->getFeature() . '</div>',
        ];
      $form['#attached']['library'][] = 'cucumber_ui/ace-editor';
    }

    // List of features in the selected cucumber features folder.
    $features_options = $this->getExistingFeatures();
    $features_default_value = 'default';
    if (count($features_options) > 0) {
      if (!isset($features_options['default'])) {
        $features_default_value = array_key_first([$features_default_value]);
      }
    }
    $form['cucumber_ui_new_scenario']['cucumber_ui_feature'] = [
      '#type' => 'radios',
      '#title' => $this->t('Feature'),
      '#options' => $features_options,
      '#default_value' => $features_default_value,
      '#suffix' => '</div></div></div>',
      ];

    $form['cucumber_ui_scenario_output'] = [
      '#type' => 'markup',
      '#markup' => '<div class="layout-column layout-column--half">
            <div class="panel">
              <h3 class="panel__title">' . $this->t('Scenario output') . '</h3>
              <div id="cucumber-ui-scenario-output" class="panel__content">',
      ];

    $form['cucumber_ui_run'] = [
      '#type' => 'button',
      '#value' => $this->t('Run >>'),
      '#ajax' => [
      'callback' => '::runSingleTest',
      'event' => 'click',
      'wrapper' => 'cucumber-ui-output',
      'progress' => [
        'type' => 'throbber',
        'message' => $this->t('Running the testing feature...'),
      ],
      ],
      ];

    $form['cucumber_ui_save'] = [
      '#type' => 'button',
      '#value' => $this->t('Save'),
      '#ajax' => [
      'callback' => '::saveSingleTest',
      'event' => 'click',
      'wrapper' => 'cucumber-ui-save-output',
      'progress' => [
        'type' => 'throbber',
        'message' => $this->t('Saving the testing feature...'),
      ],
      ],
      ];

    $form['cucumber_ui_create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download'),
      '#attribute' => [
      'id' => 'cucumber-ui-create',
      'classes' => ['button'],
      ],
      ];

    $form['cucumber_ui_output'] = [
      '#title' => $this->t('Tests output'),
      '#type' => 'markup',
      '#markup' => '<div id="cucumber-ui-output"><div id="cucumber-ui-output-inner"></div></div></div>',
      ];

    $form['cucumber_ui_save_output'] = [
      '#title' => $this->t('Tests save'),
      '#type' => 'markup',
      '#markup' => '<div id="cucumber-ui-save-output"><div id="cucumber-ui-save-inner"></div></div></div>',
      ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('cucumber_ui.settings');

    $features_path = $config->get('features_path');
    $editing_mode = $config->get('editing_mode');

      $formValues = $form_state->getValues();

      if ($editing_mode == 'free_text') {
        $content = $formValues['free_text'];
      }

      $featureName = preg_split('#\r?\n#', ltrim($content), 2)[0];
      $featureName = str_replace(array("Feature : ", "Feature: "), "", $featureName);
      $featureName = str_replace(" ", "-", $featureName);
      $featureName = strtolower($featureName);
      
      $file = $features_path . '/' . $featureName . '-dl.feature';

      $handle = fopen($file, 'w+');
      fwrite($handle, $content);
      fclose($handle);

      $file_size = filesize($file);
      $response = new Response();
      $response->headers->set('Content-Type', 'text/x-cucumber');
      $response->headers->set('Content-Disposition', 'attachment; filename="' . $featureName . '"');
      $response->headers->set('Pragma', 'no-cache');
      $response->headers->set('Content-Transfer-Encoding', 'binary');
      $response->headers->set('Content-Length', $file_size);
      $form_state->disableRedirect();
      readfile($file);
      unlink($file);
      return $response->send();
  }

  /**
   * Get existing features.
   */
  public function getExistingFeatures() {

    $config = $this->configFactory->getEditable('cucumber_ui.settings');

    $config_path = $config->get('config_path');
    $features_path = $config->get('features_path');

    $features = [];

    // $features_path = $config_path . '/' . $features_path;
    if ($this->fileSystem->prepareDirectory($features_path, FileSystemInterface::CREATE_DIRECTORY)) {
      if ($handle = opendir($features_path)) {
        while (FALSE !== ($file = readdir($handle))) {
          if (preg_match('/\.feature$/', $file)) {
            $feature = preg_replace('/\.feature$/', '', $file);
            $name = $file;
            $features[$feature] = $name;
          }
        }
      }
    }
    else {
      $this->messenger->addError($this->t('The Features directory does not exists or is not writable.'));
    }

    if (count($features) < 1) {
      $features['default'] = 'default.feature';
    }

    return $features;
  }

  /**
   * Get additional features.
   */
  public function getFeature($feature_name = 'default.feature') {
    $config = $this->configFactory->getEditable('cucumber_ui.settings');

    $features_path = $config->get('features_path');

    $default_feature_path = $features_path . '/' . $feature_name;

    if (file_exists($default_feature_path)) {
      return file_get_contents($default_feature_path);
    }
    else {
      $default_feature = '
Feature: Default festing feature

    Scenario: Check Most viewed at home page
    Given I am on homepage
        Then I should see "Welcome"
            ';
      return trim($default_feature);
    }

  }

  /**
   * Run a single test.
   */
  public function saveSingleTest(array &$form, FormStateInterface $form_state) {

    $config = $this->configFactory->getEditable('cucumber_ui.settings');

    $features_path = $config->get('features_path');
    $editing_mode = $config->get('editing_mode');

      $formValues = $form_state->getValues();

      if ($editing_mode == 'free_text') {
        $content = $formValues['free_text'];
      }
      $featureName = preg_split('#\r?\n#', ltrim($content), 2)[0];
      $featureName = str_replace(array("Feature : ", "Feature: "), "", $featureName);
      $featureName = str_replace(" ", "-", $featureName);
      $featureName = strtolower($featureName);
      
      $file = $features_path . '/' . $featureName . '.feature';

      $handle = fopen($file, 'w+');
      fwrite($handle, $content);
      fclose($handle);

    return $form['cucumber_ui_save_output'];
  }
  /**
   * Run a single test.
   */
  public function runSingleTest(array &$form, FormStateInterface $form_state) {
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

    $formValues = $form_state->getValues();
    // Write to temporary file.
    $file_user_time = 'user-' . date('Y-m-d_h-m-s');
    $file = $features_path . '/' . $file_user_time . '.feature';

    if ($editing_mode == 'free_text') {
      $test = "@currentTest\n" . $formValues['free_text'];
    }

    $handle = fopen($file, 'w+');
    fwrite($handle, $test);
    fclose($handle);

    // Run file.
    $test_file = $features_path . '/' . $file_user_time . '.feature';
    $command = '';

    if ($html_report) {

      if (isset($html_report_dir) && $html_report_dir != '') {

        if ($this->fileSystem->prepareDirectory($html_report_dir, FileSystemInterface::CREATE_DIRECTORY)) {
          $command = "cd $config_path; yarn nightwatch --tags @currentTest $html_report_format";
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
          $command = "cd $config_path; yarn nightwatch --tags @currentTest $html_report_format";
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
    else{
        unlink($html_report_format."/*.json");
    }

    $command .= ';';
    $output = shell_exec($command);

    if (isset($output)) {
      $report_html_file_name_and_path = $html_report_dir . '/index.html';

      $report_html_handle = fopen($report_html_file_name_and_path, 'r');
      $report_html = fread($report_html_handle, filesize($report_html_file_name_and_path));
      if (isset($report_html)) {
        fclose($report_html_handle);

        if (!$save_user_testing_features) {
          unlink($file);
        }
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
   * Given a form_state, return a Cucumber scenario.
   */
  public function generateScenario($formValues) {
    $scenario = "";
    if ($formValues['cucumber_ui_javascript']) {
      $scenario .= " @javascript";
    }
    $title = $formValues['cucumber_ui_title'];
    $scenario .= "\nScenario: $title\n";

    $steps_count = count($formValues['cucumber_ui_steps']);

    for ($i = 0; $i < $steps_count; $i++) {
      $type = $formValues['cucumber_ui_steps'][$i]['type'];
      $step = $formValues['cucumber_ui_steps'][$i]['step'];

      if (!empty($type) && !empty($step)) {
        $step = preg_replace('/\n\|/', "\n  |", preg_replace('/([:\|])\|/', "$1\n|", $step));
        $scenario .= "  $type $step\n";
      }
    }

    return $scenario;
  }

  /**
   * Cucumber Ui add step AJAX.
   */
  public function ajaxAddStep($form, $form_state) {
    return $form['cucumber_ui_new_scenario']['cucumber_ui_steps'];
  }

}
