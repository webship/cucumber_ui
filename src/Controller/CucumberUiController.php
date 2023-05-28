<?php

namespace Drupal\cucumber_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
     */
    public function __construct(ConfigFactory $config_factory, MessengerInterface $messenger, Request $current_request, PrivateTempStoreFactory $temp_store_factory, RendererInterface $renderer)
    {
        $this->configFactory = $config_factory;
        $this->messenger = $messenger;
        $this->currentRequest = $current_request;
        $this->tempStore = $temp_store_factory;
        $this->renderer = $renderer;
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
            $container->get('renderer')
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
     * Get Cucumber test status.
     */
    public function getTestStatus()
    {

        $report_url = new Url('cucumber_ui.report');
        $output = '<iframe id="cucumber-ui-output-iframe" src="' . $this->currentRequest->getSchemeAndHttpHost() . $report_url->toString() . '" width="100%" height="100%"></iframe>';

        $beaht_ui_tempstore_collection = $this->tempStore->get('cucumber_ui');
        $pid = $beaht_ui_tempstore_collection->get('cucumber_ui_pid');

        if ($pid && posix_kill(intval($pid), 0)) {

            return new JsonResponse(
                [
                'running' => true,
                'pid' => $pid,
                'output' => $output,
                ]
            );
        }
        else {

            return new JsonResponse(
                [
                'running' => false,
                'pid' => '',
                'output' => $output,
                ]
            );
        }

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
     * Kill running test.
     */
    public function kill()
    {
        $response = false;
        $beaht_ui_tempstore_collection = $this->tempStore->get('cucumber_ui');
        $pid = $beaht_ui_tempstore_collection->get('cucumber_ui_pid');

        if ($pid && posix_kill(intval($pid), 0)) {
            try {
                $response = posix_kill($pid, SIGKILL);
                $beaht_ui_tempstore_collection->delete('cucumber_ui_pid');
            }
            catch (Exception $e) {
                $response = false;
            }
        }
        return new JsonResponse(['response' => $response]);
    }

    /**
     * Download.
     */
    public function download($format)
    {

        $config = $this->configFactory->getEditable('cucumber_ui.settings');

        if (($format === 'html' || $format === 'txt')) {

            $headers = [
            'Content-Type' => 'text/x-cucumber',
            'Content-Disposition' => 'attachment; filename="cucumber_ui_output.' . $format . '"',
            ];

            foreach ($headers as $key => $value) {
                drupal_add_http_header($key, $value);
            }

            if ($format === 'html') {
                $html_report_dir = $config->get('html_report_dir');
                $output = $html_report_dir . '/index.html';
                readfile($output);
            }
            elseif ($format === 'txt') {
                drupal_add_http_header('Connection', 'close');
                $log_report_dir = $config->get('log_report_dir');
                $output = $log_report_dir . '/cucumber-ui-test.log';
                $plain = file_get_contents($output);
                echo drupal_html_to_text($plain);
            }
        }
        else {
            $this->messenger->addError($this->t('Output file not found. Please run the tests again in order to generate it.'));
            drupal_goto('cucumber_ui.run_tests');
        }
    }

    /**
     * Auto complete cucumber definition steps.
     */
    public function getAutocompleteDefinitionSteps()
    {

        $config = $this->configFactory->getEditable('cucumber_ui.settings');
        $cucumber_config_path = $config->get('config_path');

        $command = "cd $cucumber_config_path; $cucumber_bin -dl | sed 's/^\s*//g'";
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
        // $output = nl2br($output);
        $build = [
        '#markup' => $this->formatCucumberSteps($output),
        ];
        return $build;
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
        // $output = nl2br($output);
        $build = [
        '#markup' => $this->formatCucumberSteps($output),
        ];
        return $build;
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

        $cucumberList += explode("BEHAT_UI_DELIMITER", $output);
        sort($cucumberList);

        return new JsonResponse($cucumberList);
    }

}
