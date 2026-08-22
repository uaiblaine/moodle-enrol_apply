@enrol @enrol_apply
Feature: Enrolment upon approval
  In order to control who joins my course
  As a teacher
  I need to review and approve enrolment applications before users get access

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name | Apply for this course |
    And I log out
    # The field set has no generator behind it, and which fields are collected is covered
    # exhaustively by tests/local/fields_test.php. One field keeps these scenarios thin.
    And the "C1" apply enrolment method asks for "s_city"

  Scenario: A student applies and gets no course access until the application is approved
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I press "Start application"
    Then I should see "Check your details"
    And I set the field "City/town" to "Campinas"
    And I set the field "'City/town' is up to date" to "1"
    And I press "Submit application"
    Then I should see "Application submitted"
    And I am on "Course 1" course homepage
    And I should not see "New section"

  Scenario: A teacher approves a pending application and the student gains access
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    Then I should see "Student 1"
    And I set the field "Select Student 1" to "1"
    And I set the field "With selected users..." to "Confirm requests"
    And I press "Go"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "New section"

  Scenario: A teacher cancels a pending application and the student stays out
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    And I set the field "Select Student 1" to "1"
    And I set the field "With selected users..." to "Cancel requests"
    And I press "Go"
    Then I should see "Nothing to display"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should not see "New section"

  # Capability enforcement is covered by tests/lib_test.php: asserting on it here would
  # mean asserting on an exception page, which behat's exception hook fails by design.
