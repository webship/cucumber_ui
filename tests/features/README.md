# Cucumber UI - webship-js feature scenarios

| File | Covers |
|------|--------|
| `01-01-01-users-login.feature` | Per-role login; the first scenario provisions the testing users. |
| `02-01-01-cucumber-ui-module-enabled.feature` | The module is enabled and its permissions are listed. |
| `02-02-01-admin-menu-link.feature` | The Configuration > Development menu link. |
| `03-01-01-run-tests-page.feature` | The run tests page, its local tasks and runner controls. |
| `03-02-01-create-test-page.feature` | The create test page, its editor and action buttons. |
| `03-03-01-settings-page.feature` | The settings form and the shipped default values. |
| `04-01-01-admin-theme-assets.feature` | The Claro stylesheet and the page scripts are attached. |
| `05-01-01-access-control.feature` | Anonymous and authenticated users are denied. |

The asset scenarios read `link`/`script` URLs, so the site must serve
library files unaggregated (`system.performance css.preprocess 0` and
`js.preprocess 0`).
