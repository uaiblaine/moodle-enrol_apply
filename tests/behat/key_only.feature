@enrol @enrol_apply
Feature: Enrolment with a group key only
  In order to enrol a group's members with no review at all
  As a teacher
  I need a key-only method to enrol a valid key at once, in two steps, and to let nobody in without one

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "groups" exist:
      | name    | course | idnumber | enrolmentkey |
      | Turma B | C1     | TB       | Turma-B-2027 |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    # No instance name: an unnamed key-only method is called after what it does.
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | What a group enrolment key does | Key only: a valid key enrols at once, and nobody can enrol without one |
    And I log out
    And the "C1" apply enrolment method asks for ""

  # No @javascript: the page transport, where Continue and Enrol me are the form's own buttons.
  Scenario: The key comes first, the rest after it, and a wrong key offers no way round it
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Enrolment with a group key"
    And I should see "This course needs the group enrolment key your teacher gave you."
    When I press "Enrol with a key"
    And I set the field "Group enrolment key" to "Turma-Z-2027"
    And I press "Continue"
    Then I should see "This key is not valid for this course. Check it and try again."
    And I should not see "Send my application for review without the key"
    And I set the field "Group enrolment key" to "Turma-B-2027"
    And I press "Continue"
    And I should see "You will join Turma B."
    And I press "Enrol me"
    And I should see "Your group key was accepted and you have joined Turma B"

  # @javascript: the modal's footer follows the step through enrol_page.js.
  @javascript
  Scenario: In the modal the footer's submit waits for step 2
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I press "Enrol with a key"
    Then "Submit application" "button" in the "Enrolment with a group key" "dialogue" should not be visible
    And I set the field "Group enrolment key" to "Turma-B-2027"
    And I press "Continue"
    And I should see "You will join Turma B."
    And I click on "Enrol me" "button" in the "Enrolment with a group key" "dialogue"
    And I should see "Your group key was accepted and you have joined Turma B"
