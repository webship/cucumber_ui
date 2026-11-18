<?php

namespace Drupal\Tests\cucumber_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Keeps the Cucumber UI settings as their schema declares them.
 *
 * The test site checks every configuration it saves against its schema, so
 * installing the module and saving the settings form fail on a mismatch.
 */
#[Group('cucumber_ui')]
#[RunTestsInSeparateProcesses]
class SettingsSchemaTest extends BrowserTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['cucumber_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The shipped settings, and the settings form saves, match the schema.
   */
  public function testSettingsMatchTheirSchema(): void {
    $this->assertConfigSchemaByName('cucumber_ui.settings');

    $this->drupalLogin($this->drupalCreateUser(['administer cucumber ui settings']));
    $this->drupalGet('admin/config/development/cucumber-ui/settings');
    $this->submitForm(['html_report_format' => 'html'], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $settings = $this->config('cucumber_ui.settings')->getRawData();
    $this->assertSame('html', $settings['html_report_format']);
    foreach (['submit', 'form_build_id', 'form_token', 'form_id', 'op'] as $key) {
      $this->assertArrayNotHasKey($key, $settings);
    }
    $this->assertConfigSchemaByName('cucumber_ui.settings');
  }

}
