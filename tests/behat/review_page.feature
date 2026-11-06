@enrol @enrol_apply
Feature: One application read as a decision funnel
  In order to decide an application knowing what the decision does
  As a teacher
  I need the review page to say what weighs on it, what confirming does, and what I can adjust

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | teacher        |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name | Apply for this course |
    And I log out
    And the "C1" apply enrolment method asks for ""

  # @javascript: the sentence follows the ticks through enrol_apply/review, which only a browser runs.
  # The key's group is ticked as the page loads, because the approval would join it anyway.
  @javascript
  Scenario: The outcome says what confirming does, and adjusting the groups changes it
    Given the following "groups" exist:
      | name    | course | idnumber | enrolmentkey |
      | Turma A | C1     | TA       | Turma-A-2027 |
      | Turma B | C1     | TB       |              |
    And "student1" has applied to "C1" with the key of "Turma A"
    And I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    When I click on "Decide this application" "link" in the "Student 1" "table_row"
    Then I should see "What weighs on the decision"
    And I should see "On confirming"
    And I should see "Student 1 joins the course as Student and the group Turma A, and is notified of the approval."
    And I click on "Adjust groups and role" "text"
    And I set the field "Turma B" to "1"
    And I should see "Student 1 joins the course as Student and the groups Turma A, Turma B, and is notified of the approval."
    And I press "Confirm this application"
    And I should see "The selected enrolment applications have been updated."
    And I am on the "C1" "groups" page
    And I set the field "groups" to "Turma B (1)"
    And the "members" select box should contain "Student 1 (student1@example.com)"

  # No @javascript: a viewer's page is a plain page with nothing to submit.
  Scenario: A viewer reads the application with no decision to take
    Given the following "groups" exist:
      | name    | course | idnumber | enrolmentkey |
      | Turma A | C1     | TA       | Turma-A-2027 |
    And the following "permission overrides" exist:
      | capability                    | permission | role    | contextlevel | reference |
      | enrol/apply:viewapplications  | Allow      | teacher | Course       | C1        |
    And "student1" has applied to "C1" with the key of "Turma A"
    And I log in as "teacher2"
    And I am on the "C1" "enrol_apply > manage applications" page
    When I click on "Review" "link" in the "Student 1" "table_row"
    Then I should see "Read only"
    And I should see "You can view this application but not decide it."
    And I should see "Key accepted: Turma A"
    And I should see "What weighs on the decision"
    And I should not see "On confirming"
    And I should not see "Confirm this application"

  # @javascript: core's autocomplete is a browser control, and Behat drives it only through
  # "I set the field". Ten groups are more than the eight a page shows as cards, and the method
  # gives none, so "Adjust" is open as the page loads.
  @javascript
  Scenario: In a course with many groups the decider adds one by searching
    Given the following "groups" exist:
      | name     | course | idnumber |
      | Turma 1  | C1     | T1       |
      | Turma 2  | C1     | T2       |
      | Turma 3  | C1     | T3       |
      | Turma 4  | C1     | T4       |
      | Turma 5  | C1     | T5       |
      | Turma 6  | C1     | T6       |
      | Turma 7  | C1     | T7       |
      | Turma 8  | C1     | T8       |
      | Turma 9  | C1     | T9       |
      | Turma 10 | C1     | T10      |
    And "student1" has applied to "C1" with the key of ""
    And I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    When I click on "Decide this application" "link" in the "Student 1" "table_row"
    Then I should see "Add another group"
    And I should see "Student 1 joins the course as Student, without a group, and is notified of the approval."
    And I set the field "Add another group" to "Turma 9 (T9)"
    And I should see "Student 1 joins the course as Student and the group Turma 9, and is notified of the approval."
    And I press "Confirm this application"
    And I should see "The selected enrolment applications have been updated."
    And I am on the "C1" "groups" page
    And I set the field "groups" to "Turma 9 (1)"
    And the "members" select box should contain "Student 1 (student1@example.com)"
