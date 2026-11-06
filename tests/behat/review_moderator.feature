@enrol @enrol_apply
Feature: A moderator's approval keeps the method's groups outside their own
  In order to decide my groups' applications without undoing what the method gives
  As a moderator in a course with separate groups
  I need an approval to keep the method's groups I may not choose, and the page to say so

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | groupmode |
      | Course 1 | C1        | topics | 1         |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | C1     | teacher |
    And the following "groups" exist:
      | name       | course | idnumber | enrolmentkey |
      | Turma A    | C1     | TA       | Turma-A-2027 |
      | Turma C    | C1     | TC       |              |
      | Turma 2027 | C1     | T27      |              |
    And the following "group members" exist:
      | user     | group |
      | teacher2 | TA    |
      | teacher2 | TC    |
    And the following "permission overrides" exist:
      | capability                     | permission | role    | contextlevel | reference |
      | enrol/apply:manageapplications | Allow      | teacher | Course       | C1        |
      | enrol/apply:choosedecision     | Allow      | teacher | Course       | C1        |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | What a group enrolment key does | Key only, reviewed: a valid key sends the application for review, and nobody can apply without one |
    And I log out
    And the "C1" apply enrolment method asks for ""
    And the "C1" apply enrolment method also joins "Turma 2027"
    And "student1" has applied to "C1" with the key of "Turma A"

  # @javascript: the sentence follows the ticks through enrol_apply/review, which only a browser runs.
  # Turma 2027 is the method's and not the moderator's to choose: it has no card, the sentence
  # names it after the ticked groups, the line under the cards (inside "Adjust") says it stays, and
  # the approval joins it.
  @javascript
  Scenario: Ticking another own group keeps the method's group outside them
    Given I log in as "teacher2"
    And I am on the "C1" "enrol_apply > manage applications" page
    When I click on "Review" "link" in the "Student 1" "table_row"
    Then I should see "Student 1 joins the course as Student and the groups Turma A, Turma 2027, and is notified of the approval."
    And I click on "Adjust groups and role" "text"
    And I should see "The method also gives groups you may not choose, and an approval keeps them: Turma 2027."
    And I set the field "Turma C" to "1"
    And I should see "Student 1 joins the course as Student and the groups Turma A, Turma C, Turma 2027, and is notified of the approval."
    And I press "Confirm this application"
    And I should see "The selected enrolment applications have been updated."
    And I log out
    And I log in as "admin"
    And I am on the "C1" "groups" page
    And I set the field "groups" to "Turma 2027 (1)"
    And the "members" select box should contain "Student 1 (student1@example.com)"
