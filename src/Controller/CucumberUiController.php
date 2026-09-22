<?php

namespace Drupal\cucumber_ui\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\cucumber_ui\CucumberUiFragments;
use Drupal\cucumber_ui\CucumberUiRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Default Cucumber Ui controller for the Cucumber Ui module.
 */
class CucumberUiController extends ControllerBase
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
     * The current request.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    protected $currentRequest;

    /**
     * The temp store object.
     *
     * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
     */
    protected $tempStore;

    /**
     * The renderer.
     *
     * @var \Drupal\Core\Render\RendererInterface
     */
    protected $renderer;

    /**
     * The Cucumber UI runner.
     *
     * @var \Drupal\cucumber_ui\CucumberUiRunner
     */
    protected $runner;

    /**
     * The Cucumber UI fragment builder.
     *
     * @var \Drupal\cucumber_ui\CucumberUiFragments
     */
    protected $fragments;

    /**
     * Constructs a CucumberUIController object.
     *
     * @param \Drupal\Core\Config\ConfigFactory              $config_factory
     *   The config factory service.
     * @param \Drupal\Core\Messenger\MessengerInterface      $messenger
     *   The messenger service.
     * @param \Symfony\Component\HttpFoundation\Request      $current_request
     *   The current request.
     * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
     *   The temp store factory.
     * @param \Drupal\Core\Render\RendererInterface          $renderer
     *   The renderer.
     * @param \Drupal\cucumber_ui\CucumberUiRunner            $runner
     *   The Cucumber UI runner.
     * @param \Drupal\cucumber_ui\CucumberUiFragments        $fragments
     *   The Cucumber UI fragment builder.
     */
    public function __construct(ConfigFactory $config_factory, MessengerInterface $messenger, Request $current_request, PrivateTempStoreFactory $temp_store_factory, RendererInterface $renderer, CucumberUiRunner $runner, CucumberUiFragments $fragments)
    {
        $this->configFactory = $config_factory;
        $this->messenger = $messenger;
        $this->currentRequest = $current_request;
        $this->tempStore = $temp_store_factory;
        $this->renderer = $renderer;
        $this->runner = $runner;
        $this->fragments = $fragments;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('config.factory'),
            $container->get('messenger'),
            $container->get('request_stack')->getCurrentRequest(),
            $container->get('tempstore.private'),
            $container->get('renderer'),
            $container->get('cucumber_ui.runner'),
            $container->get('cucumber_ui.fragments')
        );
    }

    /**
     * Get Cucumber test status report.
     */
    public function getTestStatusReport()
    {
        $config = $this->configFactory->getEditable('cucumber_ui.settings');

        $html_report = $config->get('html_report');
        $html_report_dir = $config->get('html_report_dir');
        $log_report_dir = $config->get('log_report_dir');
        $json_report = $config->get('json_report');
        $json_report_dir = $config->get('json_report_dir');

        $output = '';
        if ($html_report) {
            if (isset($html_report_dir) && $html_report_dir != '') {

                $html_report = $html_report_dir . '/index.html';

                if ($html_report && file_exists($html_report)) {
                    $output = file_get_contents($html_report);
                }
                else {
                    $output = $this->t('No HTML test report yet!');
                }
            }

        }
        else if ($json_report) {
            if (isset($json_report_dir) && $json_report_dir != '') {

                $json_report = $json_report_dir . '/index.json';

                if ($json_report && file_exists($json_report)) {
                    $output = file_get_contents($json_report);
                }
                else {
                    $output = $this->t('No JSON test report yet!');
                }
            }
        }
        else {

            if (isset($log_report_dir) && $log_report_dir != '') {

                $log_report = $log_report_dir . '/cucumber-ui-test.log';

                if ($log_report && file_exists($log_report)) {
                    $file_content = file_get_contents($log_report);
                    $output = nl2br(htmlentities($file_content ?? ''));
                }
                else {
                    $output = $this->t('No Console log test report yet!');
                }
            }
        }

        $build = [
        '#theme' => 'cucumber_ui_report',
        '#output' => $output,
        '#name' => "Cucumber UI report",
        '#cache' => ['max-age' => 0],
        ];

        $build_output = $this->renderer->renderRoot($build);
        $response = new Response();
        $response->setContent($build_output);
        return $response;

    }

    /**
     * Get the Cucumber test status region.
     *
     * Served to the HTMX poll of the run tests screen. When a run has just
     * finished the response also carries an `HX-Trigger` header, which makes
     * the tests output region reload itself.
     *
     * @return array
     *   A render array holding the status region.
     */
    public function getTestStatus()
    {
        $tempstore = $this->tempStore->get('cucumber_ui');
        $was_running = (bool) $tempstore->get('cucumber_ui_was_running');
        $is_running = $this->fragments->runningPid() !== NULL;

        if ($was_running !== $is_running) {
            $tempstore->set('cucumber_ui_was_running', $is_running);
        }

        $build = $this->fragments->buildStatus();

        if ($was_running && !$is_running) {
            (new Htmx())
                ->triggerHeader(CucumberUiFragments::FINISHED_EVENT)
                ->applyTo($build);
        }

        return $build;
    }

    /**
     * Get the Cucumber tests output region.
     *
     * @return array
     *   A render array holding the tests output region.
     */
    public function getTestOutput()
    {
        return $this->fragments->buildOutput();
    }

    /**
     * Auto complete Step.
     */
    public function autocompleteStep(Request $request)
    {
        $matches = [];

        $input = $request->query->get('q');

        if (!$input) {
            return new JsonResponse($matches);
        }

        $input = Xss::filter($input);

        $steps = explode('<br />', $this->getAutocompleteDefinitionSteps());
        foreach ($steps as $step) {
            $title = preg_replace('/^\s*(Given|Then|When|And|But) \/\^/', '', $step);
            $title = preg_replace('/\$\/$/', '', $title);
            if (preg_match('/' . preg_quote($input) . '/', $title)) {
                $matches[] = ['value' => $title, 'label' => $title];
            }
        }

        return new JsonResponse($matches);
    }

    /**
     * Kill the running test process.
     *
     * @return array
     *   A render array holding the refreshed status region.
     */
    public function kill()
    {
        $cucumber_ui_tempstore_collection = $this->tempStore->get('cucumber_ui');
        $pid = $cucumber_ui_tempstore_collection->get('cucumber_ui_pid');

        if ($pid && posix_kill((int) $pid, 0)) {
            // SIGKILL is only defined when ext-pcntl is loaded, so use its
            // POSIX signal number directly.
            posix_kill((int) $pid, 9);
            $cucumber_ui_tempstore_collection->delete('cucumber_ui_pid');
            $cucumber_ui_tempstore_collection->set('cucumber_ui_was_running', FALSE);
            $this->messenger->addStatus($this->t('The Cucumber test process was killed.'));
        }

        $build = $this->fragments->buildStatus();
        (new Htmx())
            ->triggerHeader(CucumberUiFragments::FINISHED_EVENT)
            ->applyTo($build);

        return $build;
    }

    /**
     * Save the Gherkin feature of the create test screen.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request, carrying the editor content.
     *
     * @return array
     *   A render array holding the save output region.
     */
    public function saveFeature(Request $request)
    {
        $content = (string) $request->request->get('free_text', '');

        if (trim($content) === '') {
            return $this->buildSaveOutput($this->t('Nothing to save: the feature is empty.'));
        }

        $file = $this->runner->writeFeature($this->runner->featureName($content) . '.feature', $content);

        if ($file === NULL) {
            return $this->buildSaveOutput($this->t('The testing feature could not be saved.'));
        }

        return $this->buildSaveOutput($this->t('Saved the testing feature to @file', ['@file' => $file]));
    }

    /**
     * Run the Gherkin feature of the create test screen.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   The current request, carrying the editor content.
     *
     * @return array
     *   A render array holding the tests output region.
     */
    public function runFeature(Request $request)
    {
        $config = $this->configFactory->get('cucumber_ui.settings');
        $content = (string) $request->request->get('free_text', '');
        $file = NULL;

        if (trim($content) !== '') {
            $file = $this->runner->writeFeature(
                'user-' . date('Y-m-d_H-i-s') . '.feature',
                "@currentTest\n" . $content
            );
        }

        $this->runner->run('--tags @currentTest');

        if ($file !== NULL && file_exists($file) && !$config->get('save_user_testing_features')) {
            unlink($file);
        }

        return $this->fragments->buildOutput();
    }

    /**
     * Wrap a save result message in the region the create test screen swaps.
     *
     * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
     *   The message to show.
     *
     * @return array
     *   A render array.
     */
    protected function buildSaveOutput($message)
    {
        return [
        '#type' => 'container',
        '#attributes' => ['id' => 'cucumber-ui-save-output'],
        '#cache' => ['max-age' => 0],
        'message' => ['#markup' => $message],
        ];
    }

    /**
     * Download.
     */
    public function download($format)
    {

        $config = $this->configFactory->getEditable('cucumber_ui.settings');

        if ($format === 'html') {
            $output = $config->get('html_report_dir') . '/index.html';
            $type = 'text/html';
        }
        elseif ($format === 'txt') {
            $output = $config->get('log_report_dir') . '/cucumber-ui-test.log';
            $type = 'text/plain';
        }
        else {
            $output = NULL;
            $type = '';
        }

        if ($output === NULL || !file_exists($output)) {
            $this->messenger->addError($this->t('Output file not found. Please run the tests again in order to generate it.'));
            return $this->redirect('cucumber_ui.run_tests');
        }

        return new Response(
            file_get_contents($output), 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="cucumber_ui_output.' . $format . '"',
            ]
        );
    }

    /**
     * Auto complete cucumber definition steps.
     */
    public function getAutocompleteDefinitionSteps()
    {

        $config = $this->configFactory->getEditable('cucumber_ui.settings');
        $cucumber_config_path = $config->get('config_path');

        $command = "cd $cucumber_config_path; yarn nightwatch -dl | sed 's/^\s*//g'";
        $output = shell_exec($command);
        $output = nl2br(htmlentities($output ?? ''));

        $output = str_replace('default |', '', $output);
        $output = str_replace('Given', '', $output);
        $output = str_replace('When', '', $output);
        $output = str_replace('Then', '', $output);
        $output = str_replace('And', '', $output);
        $output = str_replace('But', '', $output);
        $output = str_replace('/^', '', $output);

        return $output;
    }

    /**
     * Cucumber definition steps.
     */
    public function getDefinitionSteps()
    {

        $config = $this->configFactory->getEditable('cucumber_ui.settings');
        $cucumber_config_path = $config->get('config_path');

        $cmd = "cd $cucumber_config_path; node ./node_modules/webship-js/steplist -c '$cucumber_config_path/nightwatch.conf.js'";
        $output = shell_exec($cmd);

        return $this->buildStepListBody($output);
    }

    /**
     * Cucumber definitions steps with extended info.
     */
    public function getDefinitionStepsWithInfo()
    {

        $config = $this->configFactory->getEditable('cucumber_ui.settings');
        $cucumber_config_path = $config->get('config_path');

        $command = "cd $cucumber_config_path; node ./node_modules/webship-js/steplist -i -c '$cucumber_config_path/nightwatch.conf.js'";

        $output = shell_exec($command);

        return $this->buildStepListBody($output);
    }

    /**
     * Wrap a step list in the region the create test screen swaps.
     *
     * @param string|null $output
     *   The raw output of the step list command.
     *
     * @return array
     *   A render array.
     */
    protected function buildStepListBody($output)
    {
        $steps = $this->formatCucumberSteps($output ?? '');

        return [
        '#type' => 'container',
        '#attributes' => ['class' => ['cucumber-ui-steps__body']],
        '#cache' => ['max-age' => 0],
        'steps' => [
          '#markup' => trim($steps) !== '' ? $steps : $this->t('No step definitions were found.'),
        ],
        ];
    }

    /**
     * Format Cucumber Steps.
     */
    public function formatCucumberSteps($cucumberSteps)
    {

        $formatedCucumberSteps = str_replace('Given ', '<b>Given</b> ', $cucumberSteps);
        $formatedCucumberSteps = str_replace('When ', '<b>When</b> ', $formatedCucumberSteps);
        $formatedCucumberSteps = str_replace('Then ', '<b>Then</b> ', $formatedCucumberSteps);
        $formatedCucumberSteps = str_replace('And ', '<b>And</b> ', $formatedCucumberSteps);
        $formatedCucumberSteps = str_replace('But ', '<b>But</b> ', $formatedCucumberSteps);

        return $formatedCucumberSteps;
    }

    /**
     * Get step definitions to display as autolist in ghirken textarea.
     */
    public function getDefinitionStepsJson()
    {

        $config = $this->configFactory->getEditable('cucumber_ui.settings');
        $cucumber_config_path = $config->get('config_path');

        $cmd = "cd $cucumber_config_path; node ./node_modules/webship-js/steplist -c '$cucumber_config_path/nightwatch.conf.js'";
        $output = shell_exec($cmd);

        $cucumberList = [];

        $cucumberList += explode("CUCUMBER_UI_DELIMITER", $output ?? '');
        sort($cucumberList);

        return new JsonResponse($cucumberList);
    }

}
