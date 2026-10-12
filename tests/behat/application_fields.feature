@enrol @enrol_apply
Feature: Questions an enrolment method asks of its own
  In order to decide on an application with the information it needs
  As a teacher
  I need the application form to ask my questions and the review page to show the answers

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
    And the "C1" apply enrolment method asks for ""

  Scenario: A teacher reaches the questions of the method from its configuration
    Given the "C1" apply method asks a "text" "Why this course"
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > application fields" page
    Then I should see "Application fields" in the "region-main" "region"
    And I should see "Why this course"

  # No @javascript: the page transport of the application form is the one a browser without
  # JavaScript gets, and it is what proves the answer is submitted as part of the form.
  Scenario: The application asks the question and the review page shows the answer
    Given the "C1" apply method asks a "text" "Why this course"
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I set the field "Why this course" to "To learn the basics"
    And I press "Submit application"
    Then I should see "Application submitted"
    And I log out
    When I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    And I click on "Decide this application" "link" in the "Student 1" "table_row"
    Then I should see "Why this course"
    And I should see "To learn the basics"

  # @javascript: hiding a question is core's form dependency manager, which runs in the browser.
  # The server-side half (a hidden answer is discarded, required only when shown) is PHPUnit's.
  @javascript
  Scenario: A question shows only when the earlier answer calls for it
    Given the "C1" apply method asks a select "Shift" of "Morning,Night"
    And the "C1" apply method asks a "text" "Night permit number"
    And in "C1" "Night permit number" shows when "Shift" is "Night"
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I press "Start application"
    Then I should see "Shift"
    And I should not see "Night permit number"
    When I set the field "Shift" to "Night"
    Then I should see "Night permit number"
    And I set the field "Night permit number" to "NP-42"
    And I set the field "Shift" to "Morning"
    And I should not see "Night permit number"
