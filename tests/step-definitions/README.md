# Step definitions

`cucumber-ui.steps.js` adds what webship-js and @webship-js/drupal-core-steps do not ship:

- `Then I should see a "<Label>" field` and `Then I should see the button "<Text>"`.
- `Then I should see the local task "<Title>"` for the Drupal primary tabs, in any theme that
  labels them "Primary tabs" (core's themes, UIkit Admin) or prints core's tab classes.
- `Then the page should load the asset "<file>"` for an attached CSS or JS file.

The login by handle (`Given I am a logged in user with the "<X>" user`), the module checks and the
access checks (`Then I should be denied access to "<path>"`) come from
[@webship-js/drupal-core-steps](https://www.npmjs.com/package/@webship-js/drupal-core-steps).
