Feature: Login for every configured user
  As a site administrator
  I want every account in cucumber.js worldParameters.users to log in
  So that the scenarios have known-good accounts

  The site build creates the accounts (tests/README.md): the suite only logs
  in, it never creates or deletes an account.

  Scenario: Webmaster can log in
    Given I am a logged in user with the "Webmaster" user
    Then I should be logged in

  # The Content editor role comes with the Standard profile, which CI
  # installs; a Cucumber site has no such role.
  @standard-profile
  Scenario: Content editor can log in
    Given I am a logged in user with the "Content editor" user
    Then I should be logged in

  Scenario: Authenticated user can log in
    Given I am a logged in user with the "Authenticated user" user
    Then I should be logged in
