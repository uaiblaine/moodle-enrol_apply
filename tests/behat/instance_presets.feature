@enrol @enrol_apply
Feature: Scenarios to start a new enrolment method from
  In order to start from a coherent set of settings
  As a teacher
  I need to pick a scenario that fills in a new method's settings form before I adjust and save it

  # The scenario itself needs no JavaScript (each one is a link that rebuilds the form); the feature
  # does, because "Groups offered" is an autocomplete, which Behat sets only with JavaScript.
  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | groupmode |
      | Course 1 | C1        | topics | 1         |
    And the following "groups" exist:
      | name      | course | idnumber |
      | Evening A | C1     | EA       |
      | Evening B | C1     | EB       |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"

  @javascript
  Scenario: Classes with moderators fills in the form, and the method saves what it chose
    Given I am on the "Course 1" "enrolment methods" page
    And I select "Course enrol confirmation" from the "Add method" singleselect
    And I should see "Start from a scenario"
    When I click on "Use this scenario" "link" in the "Classes with moderators" "list_item"
    Then I should see "Chosen" in the "Classes with moderators" "list_item"
    And I expand all fieldsets
    And I should see "Start from the site defaults"
    And the field "The applicant chooses from a list" matches value "1"
    And the field "The applicant must choose" matches value "Yes: no application without a group"
    And the field "Group seats apply to this method" matches value "Yes: show the places left and refuse a full group"
    And the field "Notify of new applications" matches value "The deciders of the application's group"
    And I set the field "Custom instance name" to "Evening classes"
    And I set the field "Groups offered" to "Evening A"
    And I press "Add method"
    And I am on the "Course 1" "enrolment methods" page
    And I click on "Edit" "link" in the "Evening classes" "table_row"
    And I should not see "Start from a scenario"
    And I expand all fieldsets
    And the field "The applicant chooses from a list" matches value "1"
    And the field "The applicant must choose" matches value "Yes: no application without a group"
    And the field "Who decides applications to this method?" matches value "The deciders of the application's group"
    And the field "Notify of new applications" matches value "The deciders of the application's group"
