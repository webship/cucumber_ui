# Cucumber UI

The Cucumber UI module lets any person to run automated tests and create new tests
(and also run them while they are being created).
The user can later download the updated feature with the newly created test.
It's fully customizable and the interface is very interactive/intuitive.

Features on running an existing test suite:

* The Cucumber binary and cucumber.yml can be located at any place, you just need to
  provide the path to them
* HTTP authentication, for both headless testing and real browser testing
  (Selenium)
* Tests run on background, the module checks for the process periodically and
  the output is updated on the screen (because some large test suites can take
  even hours to run)
* Kill execution
* Colored and meaningful output
* Export output as HTML or plain text

Features on creating a new test (scenario) through the interface:

* Choose feature (among the existing ones), title and whether it requires a
  real browser (i.e., needs JavaScript or not)
* Check available step types
* Choose step type from select field ("given", "when", "and" and "then")
* Auto-complete and syntax highlighting on the step fields
* Add new steps
* Remove a step
* Reorder steps
* Run test at any time (even if it's not completed yet)
* Download the updated feature with the new scenario

YAML extension is required. You can install it through
   PECL: `# pecl install yaml`

Check the example FeatureContext.php file for two examples of useful steps:

* Take screenshot (very useful for debugging specially if you run Selenium
  headless, using XVFB or something like that)
* HTTP authentication

You can run the tests using PhantomJS instead of Selenium. In order to do that,
just run PhantomJS on port 8643,
this way: `phantomjs --webdriver=8643 --cookies-file=/tmp/cookies.txt`.
In that case, you should put
the path `http://localhost:8643/wd/hub` as the `wd_host` in `cucumber.yml`. It
didn't work with PhantomJS 2.0, but it does work with PhantomJS 1.9.8.

If you don't know from where to start, please check the file
   sample-test-suite.zip.

Check [this video](http://ca.ios.ba/files/drupal/cucumberui.ogv) to understand
 better how it works.

Check the module on [Drupal.org](https://www.drupal.org/project/cucumber_ui).

## Sponsored
* [Vardot](http://www.vardot.com).
* [Webship](http://webship.org).
* [Meedan](http://meedan.org).
