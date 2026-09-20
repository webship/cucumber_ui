/**
 * @file
 * Cucumber UI steps: shared Webship login/provisioning plus UI assertions.
 */

const { Given, Then } = require('@cucumber/cucumber');
const { friendly } = require('webship-js/tests/step-definitions/webship');

/**
 * Run a step body and rethrow any failure as a tester-friendly error.
 *
 * @param {Function} body  - async function performing the step.
 * @param {string} message - human-readable description for failures.
 */
async function attempt(body, message) {
  try {
    await body();
  } catch (err) {
    throw friendly(message, err);
  }
}

/**
 * Log in as a named test user defined in cucumber.js worldParameters.users.
 *
 * The Webmaster row is the site-install super-admin. Every other row is
 * provisioned by `Given I add testing users` (see below). The same
 * phrasing is used by the other Webship suites so scenarios can move
 * between projects without re-learning step names.
 *
 * Example #1: Given I am a logged in user with the "Webmaster" user
 * Example #2: Given I am a logged in user with the "Content editor" user
 * Example #3: Given I am a logged in user with the "Authenticated user" user
 * Example #4: Given I am a logged in user with the username "Content editor" user
 * Example #5: Given I am a logged in user with "Webmaster"
 */
Given(
  /^I am a logged in user with( the)*( username)* "([^"]*)?"( user)?$/,
  async function (theCase, usernameCase, key, userCase) {
    const users = this.parameters.users || {};
    if (!(key in users)) {
      throw new Error(
        `No user named "${key}" in cucumber.js worldParameters.users`,
      );
    }
    const { username, password } = users[key];
    if (!username || !password) {
      throw new Error(
        `User "${key}" is missing username or password in worldParameters.users`,
      );
    }
    await this.page.goto(`${this.parameters.launchUrl}/user/login`);
    await this.page.getByLabel('Username').fill(username);
    await this.page.getByLabel('Password').fill(password);
    await this.page.locator('input[value="Log in"]').click();
    await this.page.waitForLoadState('networkidle');
  },
);

/**
 * Provision every non-admin user from cucumber.js worldParameters.users via
 * Drupal's /admin/people/create form. Entries flagged isAdmin: true are
 * skipped (the site-install Webmaster already exists). Idempotent: a
 * second run reports "name is already taken" and the step swallows it.
 *
 * Must be invoked while logged in as the Webmaster (or any user with the
 * "administer users" permission).
 *
 * Example #1: Given I add testing users
 * Example #2: And I add testing users
 * Example #3: When I add testing users
 * Example #4: Given I add the testing users
 * Example #5: And we add testing users
 */
Given(/^(?:I |we )?add( the)? testing users$/, async function (theCase) {
  const users = this.parameters.users || {};
  for (const [key, info] of Object.entries(users)) {
    if (info.isAdmin) continue;
    await this.page.goto(`${this.parameters.launchUrl}/admin/people/create`);
    await this.page.locator('#edit-name').fill(info.username);
    await this.page
      .locator('#edit-mail')
      .fill(info.email || `${info.username}@example.test`);
    await this.page.locator('#edit-pass-pass1').fill(info.password);
    await this.page.locator('#edit-pass-pass2').fill(info.password);
    for (const role of info.roles || []) {
      const cb = this.page.locator(`input[name="roles[${role}]"]`);
      if ((await cb.count()) > 0) await cb.check();
    }
    await this.page.locator('#edit-submit').click();
    await this.page.waitForLoadState('networkidle');
  }
});

/**
 * Resolve a form field locator by label, falling back to the label element
 * itself for inputs that are visually replaced by rich editors (Ace, file
 * widgets, etc.) which hide the underlying control.
 *
 * @param {object} page  - Playwright page.
 * @param {string} label - visible label text.
 *
 * @return {object} a Playwright locator for the label element.
 */
function fieldLocator(page, label) {
  return page
    .locator('label.form-item__label, label.form-required, label')
    .filter({
      hasText: new RegExp(
        `^\\s*${label.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\$&')}(\\s|$)`,
        'i',
      ),
    })
    .first();
}

/**
 * Assert that a form field with the given label is visible on the page.
 *
 * Example #1: Then I should see a "Feature" field
 * Example #2: Then I should see a "Username" field
 * Example #3: Then I should see a "Password" field
 * Example #4: And I should see a "HTML report directory" field
 * Example #5: And we should see a "Feature" field
 */
Then(/^(?:I |we )?should see a "([^"]*)" field$/, async function (label) {
  await attempt(async () => {
    const locator = fieldLocator(this.page, label);
    await locator.waitFor({ state: 'visible', timeout: 10000 });
  }, `Expected to find a field labeled "${label}"`);
});

/**
 * Assert that a form field with the given label (with article "an") is visible.
 *
 * Example #1: Then I should see an "Editing mode" field
 * Example #2: Then I should see an "Image" field
 * Example #3: Then I should see an "Author" field
 * Example #4: And I should see an "Options" field
 * Example #5: And we should see an "Editing mode" field
 */
Then(/^(?:I |we )?should see an "([^"]*)" field$/, async function (label) {
  await attempt(async () => {
    const locator = fieldLocator(this.page, label);
    await locator.waitFor({ state: 'visible', timeout: 10000 });
  }, `Expected to find a field labeled "${label}"`);
});

/**
 * Assert that a button with the given text is visible on the page.
 *
 * Example #1: Then I should see the button "Run cucumber tests"
 * Example #2: Then I should see the button "Save"
 * Example #3: Then I should see the button "Download"
 * Example #4: And I should see the button "Save configuration"
 * Example #5: And we should see the button "Run >>"
 */
Then(/^(?:I |we )?should see the button "([^"]*)"$/, async function (text) {
  await attempt(async () => {
    const byValue = this.page.locator(
      `input[type="submit"][value="${text}"], input[type="button"][value="${text}"]`,
    );
    if ((await byValue.count()) > 0) {
      await byValue.first().waitFor({ state: 'visible', timeout: 10000 });
      return;
    }
    await this.page
      .locator('button')
      .filter({ hasText: text })
      .first()
      .waitFor({ state: 'visible', timeout: 10000 });
  }, `Expected to find a button with text "${text}"`);
});

/**
 * Assert that a Drupal local task (primary tab) with the given title is shown.
 *
 * Example #1: Then I should see the local task "Run tests"
 * Example #2: Then I should see the local task "Create test"
 * Example #3: Then I should see the local task "Settings"
 * Example #4: And I should see the local task "Run tests"
 * Example #5: And we should see the local task "Settings"
 */
Then(
  /^(?:I |we )?should see the local task "([^"]*)"$/,
  async function (title) {
    await attempt(async () => {
      const locator = this.page
        .locator(
          'nav.tabs a, ul.tabs a, .tabs__tab a, .block-local-tasks-block a',
        )
        .filter({ hasText: new RegExp(`^\\s*${title}\\s*$`) })
        .first();
      await locator.waitFor({ state: 'attached', timeout: 10000 });
    }, `Expected to find a local task named "${title}"`);
  },
);

/**
 * Assert that the page loaded a CSS or JavaScript asset whose URL contains
 * the given file name. Drupal appends a cache-busting query string, so the
 * match is a substring match on the href/src attribute.
 *
 * Example #1: Then the page should load the asset "cucumber-ui-style.claro.css"
 * Example #2: Then the page should load the asset "cucumber-ui.run-tests.js"
 * Example #3: Then the page should load the asset "cucumber-ui.new-test.js"
 * Example #4: And the page should load the asset "cucumber-ui-style.gin.css"
 * Example #5: And the page should load the asset "cucumber-ui.ace-editor.js"
 */
Then(/^the page should load the asset "([^"]*)"$/, async function (fileName) {
  await attempt(async () => {
    const found = await this.page.evaluate((name) => {
      const urls = [
        ...document.querySelectorAll('link[rel="stylesheet"][href]'),
      ]
        .map((el) => el.getAttribute('href'))
        .concat(
          [...document.querySelectorAll('script[src]')].map((el) =>
            el.getAttribute('src'),
          ),
        );
      return urls.some((url) => url && url.includes(name));
    }, fileName);
    if (!found) {
      throw new Error(`No stylesheet or script URL contains "${fileName}".`);
    }
  }, `Expected the page to load the asset "${fileName}"`);
});
