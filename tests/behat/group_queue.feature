@enrol @enrol_apply
Feature: The approval queue says whose group each application is in, and filters by group
  In order to work through one group's applications at a time
  As a teacher
  I need the queue to name each application's key group and to narrow to one group

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | groupmode |
      | Course 1 | C1        | topics | 1         |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "groups" exist:
      | name    | course | idnumber | enrolmentkey |
      | Turma A | C1     | TA       | Turma-A-2027 |
      | Turma B | C1     | TB       | Turma-B-2027 |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | What a group enrolment key does | Key only, reviewed: a valid key sends the application for review, and nobody can apply without one |
    And I log out
    And the "C1" apply enrolment method asks for ""
    And "student1" has applied to "C1" with the key of "Turma A"
    And "student2" has applied to "C1" with the key of "Turma B"
    And "student3" has applied to "C1" with the key of ""

  # @javascript: the filter refreshes the table over AJAX, which nothing but Behat executes.
  @javascript
  Scenario: Filtering the queue by group, then by no group, and back to every group
    Given I log in as "teacher1"
    When I am on the "C1" "enrol_apply > manage applications" page
    Then I should see "Student 1"
    And I should see "Student 2"
    And I should see "Student 3"
    And I should see "Without a group"
    And I set the field "Group" to "Turma A"
    And I should see "Student 1"
    And I should not see "Student 2"
    And I should not see "Student 3"
    And I should see "1 of 3 applications"
    And I set the field "Group" to "Without a group"
    And I should see "Student 3"
    And I should not see "Student 1"
    And I should not see "Student 2"
    And I click on "Remove the filter Group: Without a group" "link"
    And I should see "Student 1"
    And I should see "Student 2"
    And I should see "Student 3"
