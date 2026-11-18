<?php

/**
 * @file
 * Post update functions for the cucumber UI module.
 */

/**
 * Remove the values of the settings form saved with the settings.
 *
 * The settings form saved its own values with the settings: the button, the
 * form build id, the form token and the form id, which the schema of
 * cucumber_ui.settings does not declare.
 */
function cucumber_ui_post_update_remove_settings_form_values(): void {
  $config = \Drupal::configFactory()->getEditable('cucumber_ui.settings');
  $stored = $config->getRawData();
  $form_values = ['submit', 'form_build_id', 'form_token', 'form_id', 'op'];
  if (array_intersect($form_values, array_keys($stored))) {
    foreach ($form_values as $key) {
      $config->clear($key);
    }
    $config->save();
  }
}
