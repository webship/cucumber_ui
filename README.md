# Cucumber UI

Create, edit and run Cucumber (Gherkin) feature tests for your site from
the Drupal administration interface.


## Table of contents

- Features
- Requirements
- Installation
- Configuration
- Usage


## Features

- A "Run tests" page at `/admin/config/development/cucumber-ui` that runs
  the Cucumber tests of the site and shows the process status, with a
  kill link while a run is in progress.
- A "Create test" page that writes a `.feature` file in an Ace-powered
  editor, with step autocomplete, and Run, Save and Download buttons.
- A "Settings" page for the Cucumber configuration path and file, the
  features directory, and the HTML, JSON and console log report
  directories.
- The test output is rendered from the generated HTML report through the
  `cucumber_ui_report` theme template, or from the console log.
- Three permissions: create tests, run all tests, and administer the
  settings.
- Claro and Gin stylesheets, attached per active admin theme.


## Requirements

Drupal core `^9 || ^10 || ^11`. No contributed module is required.

Running the tests themselves needs a Cucumber runner on the server, such
as [webship-js](https://www.npmjs.com/package/webship-js) (Playwright +
Cucumber-js), configured through the module settings.


## Installation

Install as you would normally install a contributed Drupal module:

```
composer require drupal/cucumber_ui
drush en cucumber_ui
```


## Configuration

Go to `admin/config/development/cucumber-ui/settings` and set the
Cucumber configuration directory and file name, the features directory,
and the report directories. Then grant "Create tests with Cucumber UI",
"Run all tests in Cucumber UI" and "Administer Cucumber UI settings" to
the right roles.


## Usage

Open `admin/config/development/cucumber-ui`, press "Run cucumber tests"
and watch the report in the output pane. Use the "Create test" tab to
write a new scenario, run it on its own, then save or download the
`.feature` file.


## Sponsored

- [Webship](https://webship.co).
