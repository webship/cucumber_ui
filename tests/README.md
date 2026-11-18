# Cucumber UI tests

The functional tests of Cucumber UI: Gherkin features run by
[webship-js](https://www.npmjs.com/package/webship-js) (Playwright and Cucumber)
with the Drupal steps of
[@webship-js/drupal-core-steps](https://www.npmjs.com/package/@webship-js/drupal-core-steps)
(the login by account handle, access checks, module checks). `cucumber.js` in
the project root holds the configuration.

| Folder | What it holds |
| --- | --- |
| `features/` | the features (8 files, 29 scenarios) |
| `step-definitions/` | this project's own steps (local tasks, attached assets, fields and buttons) |
| `selectors/` | named selectors for Claro |
| `reports/`, `screenshots/`, `videos/` | what a run writes (not committed) |

## From nothing to a green run

### 1. A Cucumber site in DDEV

Cucumber UI comes with the Cucumber distribution. Build a site from the
[Cucumber project template](https://www.drupal.org/project/cucumber_project):

```bash
mkdir cucumber-site && cd cucumber-site
ddev config --project-type=drupal --docroot=web
ddev start
ddev composer create-project drupal/cucumber_project:~12.0
ddev restart
```

### 2. The site, with the test accounts

The suite logs in as the accounts that `cucumber.js` lists in
`worldParameters.users`, and never creates or deletes an account: the site
build does. Choose the passwords, keep them in your environment, install the
site with the Webmaster account, add a user with no role, and turn on Cucumber
UI (the Cucumber Starter site template installs it; the Cucumber profile
does not):

```bash
export DRUPAL_ADMIN_PASS='…' DRUPAL_AUTHENTICATED_PASS='…'
ddev drush site:install cucumber --account-name=webmaster --account-mail=webmaster@example.test --account-pass="$DRUPAL_ADMIN_PASS" -y
ddev drush user:create authenticated_user --mail=authenticated_user@example.test --password="$DRUPAL_AUTHENTICATED_PASS"
ddev drush en cucumber_ui -y
ddev drush config:set system.performance css.preprocess 0 -y
ddev drush config:set system.performance js.preprocess 0 -y
```

The asset scenarios read the `link` and `script` URLs of a page, so the site
serves its library files unaggregated.

Two tags leave scenarios out on a Cucumber site: `@standard-profile` (the
Content editor's login: a Cucumber site has no Content editor role) and
`@claro` (the Claro stylesheet of Cucumber UI: a Cucumber site runs UIkit
Admin). CI builds a Standard site with this module and Claro, and runs every
scenario (`content_editor_user` with the Content editor role, passwords made
for the job): see the `webship-js-test` job in `.gitlab-ci.yml`.

### 3. Run the suite

From this project's folder, with Node.js 24 and Yarn 4 (through Corepack):

```bash
corepack enable
yarn install
npx playwright install chromium
export LAUNCH_URL=https://cucumber-site.ddev.site
export NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem"
yarn test --tags "not @wip and not @standard-profile and not @claro"
```

`NODE_EXTRA_CA_CERTS` lets Node.js trust DDEV's HTTPS certificate for the
steps that send a request outside the browser.
`yarn test:dry` checks that every step resolves to one definition, without
a browser.

### 4. What green looks like

```
25 scenarios (25 passed)
112 steps (112 passed)
```

On the Standard site of CI: 29 scenarios, 123 steps. The HTML report is
written to `tests/reports/cucumber_report.html`. A failed step leaves a
screenshot in `tests/screenshots/` and a video in `tests/videos/`.

The save scenario of the create test page writes the default feature of the
editor to the features directory of the settings (`tests/features` under the
web root, `default-testing-feature.feature`); every run writes the same file
again, so a second run is green too.

## Test users

The accounts as `cucumber.js` lists them in `worldParameters.users`. Each
value comes from the environment variable named below; `CHANGE_ME` stands for
the password you chose in step 2.

```json
{
  "Webmaster": { "username": "webmaster", "password": "CHANGE_ME", "email": "webmaster@example.test", "isAdmin": true },
  "Content editor": { "username": "content_editor_user", "password": "CHANGE_ME", "email": "content_editor_user@example.test", "roles": ["content_editor"] },
  "Authenticated user": { "username": "authenticated_user", "password": "CHANGE_ME", "email": "authenticated_user@example.test", "roles": [] }
}
```

| Handle | Variables | The account |
| --- | --- | --- |
| Webmaster | `DRUPAL_ADMIN_USER`, `DRUPAL_ADMIN_PASS`, `DRUPAL_ADMIN_MAIL` | the account the installer created |
| Content editor | `DRUPAL_EDITOR_USER`, `DRUPAL_EDITOR_PASS`, `DRUPAL_EDITOR_MAIL` | a user with the Content editor role (Standard sites) |
| Authenticated user | `DRUPAL_AUTHENTICATED_USER`, `DRUPAL_AUTHENTICATED_PASS`, `DRUPAL_AUTHENTICATED_MAIL` | a user with no role |
