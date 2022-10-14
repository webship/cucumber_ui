<?php

namespace Drupal\cucumber_ui\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Cucumber UI Settings class.
 */
class CucumberUiSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cucumber_ui_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cucumber_ui.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cucumber_ui.settings');

    $form['config_path'] = [
      '#title' => $this->t('Cucumber configuration directory path'),
      '#description' => $this->t('Directory path for Cucumber configuration. This is where the <em>nightwatch.conf.js</em> file lives. Do not include the <em>cucumber.yml</em> file and no trailing slash at the end.'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('config_path'),
      '#prefix' => '<div class="layout-row clearfix">
          <div class="layout-column layout-column--half">
            <div class="panel">
              <h3 class="panel__title">' . $this->t('Cucumber General Settings') . '</h3>
              <div class="panel__content">',
    ];

    $form['config_file'] = [
      '#title' => $this->t('Cucumber configuration file name'),
      '#description' => $this->t('The Cucumber configuration file, in the Cucumber configuration directory path. Usually <em>cucumber.yml</em>.<br />
              <b>Examples:</b>
              <ul>
                <li>./nightwatch.conf.js</li>
                <li>/var/www/html/PROJECT_FOLDER/nightwatch.conf.js</li>
              </ul>'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('config_file'),
    ];

    $form['features_path'] = [
      '#title' => $this->t('Cucumber features directory path'),
      '#description' => $this->t('The directory path that has the Gherkin script files with <em>.feature</em> extension. The path is relative to the Cucumber configuration directory path. Do not include trailing slash at the end.<br />
              <b>Examples:</b>
              <ul>
                <li>features</li>
                <li>tests/features</li>
                <li>tests/features/commerce</li>
              </ul>'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('features_path'),
    ];

    $form['html_report'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable HTML report format'),
      '#default_value' => $config->get('html_report'),
      '#description' => $this->t('Check to enable generating an HTML report for your test results.'),
      '#prefix' => '<div class="panel">
          <h3 class="panel__title">' . $this->t('HTML formatted Report') . '</h3>
          <div class="panel__content">',
    ];

    $form['html_report_dir'] = [
      '#title' => $this->t('HTML report directory'),
      '#description' => $this->t('The full absolute path for the tests/reports. No trailing slash at the end.'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('html_report_dir'),
      '#suffix' => '</div></div>',
    ];

    $form['log_report_dir'] = [
      '#title' => $this->t('Console log report directory'),
      '#description' => $this->t('The full absolute path for the tests/logs. No trailing slash at the end'),
      '#type' => 'textfield',
      '#maxlength' => 512,
      '#default_value' => $config->get('log_report_dir'),
      '#prefix' => '<div class="panel">
          <h3 class="panel__title">' . $this->t('Console Log formatted Report') . '</h3>
          <div class="panel__content">',
      '#suffix' => '</div></div></div></div></div>',
    ];

    $editing_mode_default_value = $config->get('editing_mode');
    if (empty($editing_mode_default_value)) {
      $editing_mode_default_value = 'guided_entry';
    }

    $editing_mode_options = [
      'guided_entry' => $this->t('Guided entry'),
      'free_text' => $this->t('Free text'),
    ];

    $form['editing_mode'] = [
      '#type' => 'radios',
      '#options' => $editing_mode_options,
      '#default_value' => $editing_mode_default_value,
      '#prefix' => '<div class="layout-column layout-column--half">
          <div class="panel">
            <h3 class="panel__title">' . $this->t('Editing Mode') . '</h3>
            <div class="panel__content">',
    ];

    $form['http_auth_headless_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable HTTP authentication only for headless testing.'),
      '#default_value' => $config->get('http_auth_headless_only'),
      '#description' => $this->t('Sometimes testing using Selenium (or other driver that allows JavaScript) does not handle HTTP authentication well, for example when you have some link with some JavaScript behavior attached. On these cases, you may enable this HTTP authentication only for headless testing and find another solution for drivers that allow JavaScript (for example, with Selenium + JavaScript you can use the extension Auto Auth and save the credentials on a Firefox profile).'),
    ];

    $form['needs_browser'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Needs a real browser'),
      '#default_value' => $config->get('needs_browser'),
      '#description' => $this->t('Check this if this test needs a real browser driver using Selenium - which supports JavaScript - in order to perform actions that happen without reloading the page.'),
    ];

    $form['save_user_testing_features'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Save user testing features'),
      '#default_value' => $config->get('save_user_testing_features'),
      '#description' => $this->t('Check if you want to save user testing features in the Cucumber features path.'),
      '#suffix' => '</div></div></div></div>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('cucumber_ui.settings');
    foreach ($form_state->getValues() as $key => $value) {
      // if (strpos($key, 'cucumber_ui') !== FALSE) {
        $config->set($key, $value);
      // }
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Parses a string of 'allowed values' into an array.
   *
   * @param string $string
   *   The list of allowed values in string format described in
   *   optionsExtractAllowedValues().
   *
   * @return arraynull
   *   The array of extracted key/value pairs, or NULL if the string is invalid.
   *
   * @see optionsExtractAllowedListTextValues()
   */
  public function optionsExtractAllowedListTextValues($string) {
    $values = [];

    $list = explode("\n", $string);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');

    foreach ($list as $text) {
      $value = $key = FALSE;

      // Check for an explicit key.
      $matches = [];
      if (preg_match('/(.*)\|(.*)/', $text, $matches)) {
        // Trim key and value to avoid unwanted spaces issues.
        $key = trim($matches[1]);
        $value = trim($matches[2]);
        $values[$key] = $value;
      }
      else {
        return NULL;
      }
    }

    return $values;
  }

}
