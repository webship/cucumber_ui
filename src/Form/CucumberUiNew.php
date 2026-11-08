<?php

namespace Drupal\cucumber_ui\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\cucumber_ui\CucumberUiFragments;
use Drupal\cucumber_ui\CucumberUiRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cucumber Ui New Scenarios/Feature class.
 *
 * The Run and Save controls of this screen are driven by HTMX: they post the
 * Gherkin script to their own routes and swap the returned region into place.
 *
 * @see \Drupal\cucumber_ui\Controller\CucumberUiController::runFeature()
 * @see \Drupal\cucumber_ui\Controller\CucumberUiController::saveFeature()
 */
class CucumberUiNew extends FormBase {

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
   * The Cucumber UI fragment builder.
   *
   * @var \Drupal\cucumber_ui\CucumberUiFragments
   */
  protected $fragments;

  /**
   * The Cucumber UI runner.
   *
   * @var \Drupal\cucumber_ui\CucumberUiRunner
   */
  protected $runner;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Constructs a CucumberUiNew object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\cucumber_ui\CucumberUiFragments $fragments
   *   The Cucumber UI fragment builder.
   * @param \Drupal\cucumber_ui\CucumberUiRunner $runner
   *   The Cucumber UI runner.
   */
  public function __construct(ConfigFactory $config_factory, MessengerInterface $messenger, FileSystemInterface $file_system, CucumberUiFragments $fragments, CucumberUiRunner $runner) {
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
    $this->fileSystem = $file_system;
    $this->fragments = $fragments;
    $this->runner = $runner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('config.factory'),
      $container->get('messenger'),
      $container->get('file_system'),
      $container->get('cucumber_ui.fragments'),
      $container->get('cucumber_ui.runner')
    );
    $instance->themeManager = $container->get('theme.manager');
    return $instance;
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

    $active_theme = $this->themeManager->getActiveTheme();
    $base_themes = (array) $active_theme->getBaseThemeExtensions();

    if ($active_theme->getName() === 'gin' || array_key_exists('gin', $base_themes)) {
      $form['#attached']['library'][] = 'cucumber_ui/style.gin';
    }
    elseif ($active_theme->getName() === 'claro' || array_key_exists('claro', $base_themes)) {
      $form['#attached']['library'][] = 'cucumber_ui/style.claro';
    }

    $config = $this->configFactory->get('cucumber_ui.settings');
    $editing_mode = $config->get('editing_mode');

    $form['layout'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['layout-row', 'clearfix']],
    ];

    $form['layout']['editor'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['layout-column', 'layout-column--half']],
    ];

    $form['layout']['editor']['panel'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'cucumber-ui-new-scenario',
        'class' => ['panel'],
      ],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('New Feature'),
        '#attributes' => ['class' => ['panel__title']],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['panel__content']],
      ],
    ];

    $content = &$form['layout']['editor']['panel']['content'];

    $content['cucumber_ui_steps_link'] = $this->fragments->buildStepList(
      'cucumber_ui.cucumber_dl',
      'cucumber-ui-steps-list',
      $this->t('Check available steps')
    );
    $content['cucumber_ui_steps_link_with_info'] = $this->fragments->buildStepList(
      'cucumber_ui.cucumber_di',
      'cucumber-ui-steps-info',
      $this->t('Full steps with info')
    );

    if ($editing_mode == 'free_text') {
      $content['free_text'] = [
        '#type' => 'textarea',
        '#rows' => 30,
        '#resizable' => TRUE,
        '#attributes' => ['class' => ['free-text-ace-editor']],
        '#default_value' => $this->getFeature(),
      ];
      $content['free_text_ace_editor'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->getFeature(),
        '#attributes' => ['id' => 'free_text_ace_editor'],
      ];
      $form['#attached']['library'][] = 'cucumber_ui/ace-editor';
    }

    // List of features in the selected cucumber features folder.
    $features_options = $this->getExistingFeatures();
    $features_default_value = 'default';
    if (count($features_options) > 0 && !isset($features_options['default'])) {
      $features_default_value = array_key_first($features_options);
    }
    $content['cucumber_ui_feature'] = [
      '#type' => 'radios',
      '#title' => $this->t('Feature'),
      '#options' => $features_options,
      '#default_value' => $features_default_value,
    ];

    $form['layout']['output'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['layout-column', 'layout-column--half']],
    ];

    $form['layout']['output']['panel'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['panel']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#value' => $this->t('Scenario output'),
        '#attributes' => ['class' => ['panel__title']],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'cucumber-ui-scenario-output',
          'class' => ['panel__content'],
        ],
      ],
    ];

    $output = &$form['layout']['output']['panel']['content'];

    $output['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['cucumber-ui-actions']],
    ];

    $output['actions']['cucumber_ui_run'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Run >>'),
      '#attributes' => [
        'type' => 'button',
        'id' => 'cucumber-ui-run',
        'class' => ['button'],
      ],
    ];
    $this->htmxAction(Url::fromRoute('cucumber_ui.run_feature'), '#cucumber-ui-output', '#cucumber-ui-run-progress')
      ->applyTo($output['actions']['cucumber_ui_run']);

    $output['actions']['cucumber_ui_run_progress'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('Running the testing feature…'),
      '#attributes' => [
        'id' => 'cucumber-ui-run-progress',
        'class' => ['htmx-indicator'],
      ],
    ];

    $output['actions']['cucumber_ui_save'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Save'),
      '#attributes' => [
        'type' => 'button',
        'id' => 'cucumber-ui-save',
        'class' => ['button'],
      ],
    ];
    $this->htmxAction(Url::fromRoute('cucumber_ui.save_feature'), '#cucumber-ui-save-output', '#cucumber-ui-save-progress')
      ->applyTo($output['actions']['cucumber_ui_save']);

    $output['actions']['cucumber_ui_save_progress'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $this->t('Saving the testing feature…'),
      '#attributes' => [
        'id' => 'cucumber-ui-save-progress',
        'class' => ['htmx-indicator'],
      ],
    ];

    $output['cucumber_ui_create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Download'),
      '#attributes' => ['id' => 'cucumber-ui-create'],
    ];

    $output['cucumber_ui_save_output'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'cucumber-ui-save-output'],
    ];

    $output['cucumber_ui_output'] = $this->fragments->buildOutput();

    return $form;
  }

  /**
   * Builds the HTMX behaviour shared by the Run and Save controls.
   *
   * Both controls post the Gherkin script of the editor to their own route and
   * swap the region the route returns into the screen.
   *
   * @param \Drupal\Core\Url $url
   *   The route the control posts to.
   * @param string $target
   *   The CSS selector of the region to replace.
   * @param string $indicator
   *   The CSS selector of the element shown while the request is in flight.
   *
   * @return \Drupal\Core\Htmx\Htmx
   *   The configured HTMX controls.
   */
  protected function htmxAction(Url $url, string $target, string $indicator): Htmx {
    return (new Htmx())
      ->post($url)
      ->headers(['X-CSRF-Token' => $this->fragments->csrfHeaderToken()])
      ->include('[name="free_text"]')
      ->select($target)
      ->target($target)
      ->swap('outerHTML')
      ->indicator($indicator);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $content = (string) $form_state->getValue('free_text');
    $name = $this->runner->featureName($content);
    $file = $this->runner->writeFeature($name . '-dl.feature', $content);

    if ($file === NULL) {
      return;
    }

    $response = new Response(
      file_get_contents($file),
      200,
      [
        'Content-Type' => 'text/x-cucumber',
        'Content-Disposition' => 'attachment; filename="' . $name . '.feature"',
      ]
    );
    unlink($file);
    $form_state->setResponse($response);
  }

  /**
   * Get existing features.
   */
  public function getExistingFeatures() {

    $features_path = $this->configFactory->get('cucumber_ui.settings')->get('features_path');
    $features = [];

    if ($features_path && $this->fileSystem->prepareDirectory($features_path, FileSystemInterface::CREATE_DIRECTORY)) {
      if ($handle = opendir($features_path)) {
        while (FALSE !== ($file = readdir($handle))) {
          if (preg_match('/\.feature$/', $file)) {
            $features[preg_replace('/\.feature$/', '', $file)] = $file;
          }
        }
        closedir($handle);
      }
    }
    else {
      $this->messenger->addError($this->t('The Features directory does not exist or is not writable.'));
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
    $features_path = $this->configFactory->get('cucumber_ui.settings')->get('features_path');
    $default_feature_path = $features_path . '/' . $feature_name;

    if ($features_path && file_exists($default_feature_path)) {
      return file_get_contents($default_feature_path);
    }

    return trim('
Feature: Default testing feature

    Scenario: Check Most viewed at home page
    Given I am on homepage
        Then I should see "Welcome"
    ');
  }

}
