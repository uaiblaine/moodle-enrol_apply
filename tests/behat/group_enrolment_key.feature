@enrol @enrol_apply
Feature: Group enrolment keys
  In order to let a group's members in without reviewing each of them
  As a teacher
  I need a valid group enrolment key to enrol an applicant at once, and a wrong one to leave the review open

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
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name        | Apply for this course                    |
      | Accept group enrolment keys | Yes: a valid key enrols without review   |
    And I log out
    # No profile fields: what the key does is the subject here, and the default field set would
    # add required fields and confirmations to every step.
    And the "C1" apply enrolment method asks for ""

  Scenario: A valid group key enrols the applicant at once and puts them in the group
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Have a group enrolment key?"
    When I press "Start application"
    And I set the field "Group enrolment key" to "Turma-B-2027"
    And I press "Submit application"
    # The membership itself is held by tests/key_admission_test.php; the message names the group the
    # record says the key admitted into.
    Then I should see "Your group key was accepted and you have joined Turma B"

  Scenario: A wrong key is refused, and the applicant can still send the application for review
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I press "Start application"
    And I set the field "Group enrolment key" to "Turma-Z-2027"
    And I press "Submit application"
    Then I should see "This key is not valid for this course"
    And I set the field "Send my application for review without the key" to "1"
    And I press "Submit application"
    And I should see "Application submitted"

  Scenario: Without the option, neither the card nor the form mentions a key
    Given the "C1" apply enrolment method accepts no group keys
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should not see "Have a group enrolment key?"
    And I press "Start application"
    And "Group enrolment key" "field" should not exist
