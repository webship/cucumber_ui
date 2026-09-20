# Step definitions

`cucumber-ui.steps.js` adds what webship-js does not ship:

- `Given I am a logged in user with the "<X>" user` and `Given I add testing users` (shared with the Webship modules).
- `Then I should see a "<Label>" field` and `Then I should see the button "<Text>"`.
- `Then I should see the local task "<Title>"` for the Drupal primary tabs.
- `Then the page should load the asset "<file>"` for an attached CSS or JS file.
