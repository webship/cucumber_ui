<?php

namespace Drupal\cucumber_ui\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\cucumber_ui\CucumberUiFragments;
use Drupal\cucumber_ui\CucumberUiRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Constructs a CucumberUiRunTests object.
   *
   * @param \Drupal\Core\Config\ConfigFactory         $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\cucumber_ui\CucumberUiFragments   $fragments
   *   The Cucumber UI fragment builder.
   * @param \Drupal\cucumber_ui\CucumberUiRunner      $runner
   *   The Cucumber UI runner.
   */
  public function __construct(ConfigFactory $config_factory, MessengerInterface $messenger, CucumberUiFragments $fragments, CucumberUiRunner $runner)
  {
      $this->configFactory = $config_factory;
      $this->messenger = $messenger;
      $this->fragments = $fragments;
      $this->runner = $runner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
      return new static(
          $container->get('config.factory'),
          $container->get('messenger'),
          $container->get('cucumber_ui.fragments'),
          $container->get('cucumber_ui.runner')
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

      $form['submit_button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run cucumber tests'),
      ];

      $form['cucumber_ui_status'] = $this->fragments->buildStatus();
      $form['cucumber_ui_output'] = $this->fragments->buildOutput();
      $form['cucumber_ui_output']['#title'] = $this->t('Tests output');

      return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
      $this->runner->run();
      $form_state->setRebuild();
  }

  /**
   * Validate Form.
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
      parent::validateForm($form, $form_state);
  }
}
