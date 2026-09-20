# Cucumber UI tests

[webship-js](https://www.npmjs.com/package/webship-js) (Playwright + Cucumber-js) suite, run against plain Drupal core 11.

```bash
ddev drush site:install standard --account-name=webmaster --account-pass=dD.123123ddd -y
ddev drush en cucumber_ui -y
ddev drush config:set system.performance css.preprocess 0 -y
ddev drush config:set system.performance js.preprocess 0 -y
yarn install
./node_modules/.bin/playwright install --with-deps chromium
LAUNCH_URL="https://<project>.ddev.site" yarn test
```

CI: the `webship-js-test` job in `.gitlab-ci.yml`.
