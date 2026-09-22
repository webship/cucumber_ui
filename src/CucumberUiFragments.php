<?php

namespace Drupal\cucumber_ui;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;

/**
 * Builds the HTMX driven fragments shared by the Cucumber UI screens.
 *
 * Both the run tests screen and the standalone fragment routes render the very
 * same markup, so the polling attributes stay identical across a swap and the
 * HTMX request loop keeps running after the element has been replaced.
 *
 * @see \Drupal\cucumber_ui\Controller\CucumberUiController
 * @see \Drupal\cucumber_ui\Form\CucumberUiRunTests
 */
class CucumberUiFragments {

  use StringTranslationTrait;

  /**
   * The name of the event fired when a test run stops.
   *
   * The run tests output region listens for this event, so a finished run
   * refreshes the report without polling the report itself.
   */
  const FINISHED_EVENT = 'cucumber-ui-tests-finished';

  /**
   * How often the status region asks the server for the process state.
   */
  const POLL_INTERVAL = '10s';

  /**
   * Constructs a CucumberUiFragments object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The private tempstore factory.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The CSRF token generator.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected CsrfTokenGenerator $csrfToken,
  ) {
  }

  /**
   * The process id of the running test run, when there is one.
   *
   * @return string|null
   *   The process id, or NULL when no run is in progress.
   */
  public function runningPid(): ?string {
    $pid = $this->tempStoreFactory->get('cucumber_ui')->get('cucumber_ui_pid');
    if ($pid && posix_kill((int) $pid, 0)) {
      return (string) $pid;
    }
    return NULL;
  }

  /**
   * The token HTMX sends with every state changing request.
   *
   * @return string
   *   A CSRF token for the X-CSRF-Token request header.
   *
   * @see \Drupal\Core\Access\CsrfRequestHeaderAccessCheck
   */
  public function csrfHeaderToken(): string {
    return $this->csrfToken->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  }

  /**
   * Builds the status region of the run tests screen.
   *
   * The region polls itself through HTMX and replaces itself with the response,
   * so the returned markup always carries the polling attributes again.
   *
   * @return array
   *   A render array.
   */
  public function buildStatus(): array {
    $pid = $this->runningPid();

    $build = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'cucumber-ui-status',
        'class' => $pid ? ['running'] : [],
      ],
      '#cache' => ['max-age' => 0],
      'label' => [
        '#markup' => $this->t('Status:') . ' ',
      ],
      'state' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $pid
          ? $this->t('Process: @pid Running', ['@pid' => $pid])
          : $this->t('Not running'),
      ],
    ];

    if ($pid) {
      $build['kill'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('(kill)'),
        '#attributes' => [
          'type' => 'button',
          'id' => 'cucumber-ui-kill',
          'class' => ['cucumber-ui-kill', 'link'],
        ],
      ];
      $kill = (new Htmx())
        ->post(Url::fromRoute('cucumber_ui.kill'))
        ->headers(['X-CSRF-Token' => $this->csrfHeaderToken()])
        ->confirm($this->t('Kill the running Cucumber test process?'))
        ->select('#cucumber-ui-status')
        ->target('#cucumber-ui-status')
        ->swap('outerHTML');
      $kill->applyTo($build['kill']);
    }

    $status = (new Htmx())
      ->get(Url::fromRoute('cucumber_ui.status'))
      ->trigger('every ' . self::POLL_INTERVAL)
      ->select('#cucumber-ui-status')
      ->target('this')
      ->swap('outerHTML');
    $status->applyTo($build);

    return $build;
  }

  /**
   * Builds the tests output region of the run tests screen.
   *
   * The region reloads itself when the status region reports that a run has
   * just finished, which is the only moment its content can change.
   *
   * @return array
   *   A render array.
   */
  public function buildOutput(): array {
    $build = [
      '#type' => 'container',
      '#attributes' => ['id' => 'cucumber-ui-output'],
      '#cache' => ['max-age' => 0],
      'report' => $this->buildReport(),
    ];

    $output = (new Htmx())
      ->get(Url::fromRoute('cucumber_ui.output'))
      ->trigger(self::FINISHED_EVENT . ' from:body')
      ->select('#cucumber-ui-output')
      ->target('this')
      ->swap('outerHTML');
    $output->applyTo($build);

    return $build;
  }

  /**
   * Builds the report shown inside the tests output region.
   *
   * @return array
   *   A render array holding the report iframe, the console log, or a message
   *   explaining that there is no report yet.
   */
  public function buildReport(): array {
    $config = $this->configFactory->get('cucumber_ui.settings');

    if ($config->get('html_report')) {
      $directory = $config->get('html_report_dir');
      if (empty($directory)) {
        return ['#markup' => $this->t('The HTML report directory is not configured.')];
      }
      if (!file_exists($directory . '/index.html')) {
        return ['#markup' => $this->t('No HTML report yet')];
      }
      return [
        '#type' => 'html_tag',
        '#tag' => 'iframe',
        '#value' => '',
        '#attributes' => [
          'id' => 'cucumber-ui-output-iframe',
          'src' => Url::fromRoute('cucumber_ui.report')->toString(),
          'width' => '100%',
          'height' => '100%',
          'title' => $this->t('Cucumber UI report'),
        ],
      ];
    }

    $directory = $config->get('log_report_dir');
    if (empty($directory)) {
      return ['#markup' => $this->t('The Console Log report directory is not configured.')];
    }
    $log = $directory . '/cucumber-ui-test.log';
    if (!file_exists($log)) {
      return ['#markup' => $this->t('No Log report yet')];
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => file_get_contents($log),
    ];
  }

  /**
   * Builds a lazily loaded disclosure holding a step definition list.
   *
   * The body is fetched by HTMX the first time the disclosure is opened, so the
   * (slow) step list command only runs when a tester asks for it.
   *
   * @param string $route
   *   The route serving the step list.
   * @param string $id
   *   The HTML id of the disclosure.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $title
   *   The summary text.
   *
   * @return array
   *   A render array.
   */
  public function buildStepList(string $route, string $id, $title): array {
    $build = [
      '#type' => 'details',
      '#title' => $title,
      '#attributes' => ['id' => $id, 'class' => ['cucumber-ui-steps']],
      'body' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['cucumber-ui-steps__body']],
        'placeholder' => ['#markup' => $this->t('Loading the available steps…')],
      ],
    ];

    $htmx = (new Htmx())
      ->get(Url::fromRoute($route))
      ->trigger('toggle once from:#' . $id)
      ->select('.cucumber-ui-steps__body')
      ->target('this')
      ->swap('outerHTML');
    $htmx->applyTo($build['body']);

    return $build;
  }

}
