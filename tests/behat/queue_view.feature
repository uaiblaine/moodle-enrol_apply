@enrol @enrol_apply @javascript
Feature: The approval queue of one method in its grid view
  In order to read the applications awaiting a decision as a data report
  As a teacher
  I need the queue's grid view to list, scope and search them, keeping what I ask in the address

  # Each scenario asserts what only the mounted component draws (rows in its own region, its count,
  # its chips, its tiles), never the shell's placeholder. The view is read only in this stage: the
  # deferral below is taken on the review page, as before.
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
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
    And "student1" has applied to "C1" with the key of ""
    And "student2" has applied to "C1" with the key of ""

  Scenario: The grid lists the applications under the tiles, and the table view leads back to it
    Given I log in as "teacher1"
    When I am on the "C1" "enrol_apply > applications queue grid" page
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
    And I should see "Awaiting decision" in the "[data-region='enrol_apply-tiles']" "css_element"
    When I follow "Table view"
    Then I should see "Student 1"
    And "[data-region='enrol_apply-rows']" "css_element" should not exist
    When I follow "Grid view"
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"

  Scenario: The Deferred scope lists the deferred applications, and the address keeps it
    Given I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    And I click on "Review" "link" in the "Student 2" "table_row"
    And I press "Defer this application"
    When I am on the "C1" "enrol_apply > applications queue grid" page
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
    And I click on "Deferred" "button" in the "[data-region='enrol_apply-bar']" "css_element"
    Then I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"
    # The scope is the address's status parameter: the same address, loaded again, lands on it.
    When I reload the page
    Then I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"

  Scenario: The search narrows the rows, shows as a chip once closed, and the address keeps it
    Given I log in as "teacher1"
    And I am on the "C1" "enrol_apply > applications queue grid" page
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
    When I press "Search by name"
    And I set the field "Search by name" to "Student 1"
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"
    When I press the escape key
    Then I should see "Name: Student 1" in the "[data-region='enrol_apply-chips']" "css_element"
    # The search is the address's search parameter: the same address, loaded again, lands filtered,
    # with the field open on the text in force.
    When I reload the page
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And the field "Search by name" matches value "Student 1"
    When I press the escape key
    Then I should see "Name: Student 1" in the "[data-region='enrol_apply-chips']" "css_element"
    When I press "Remove filter Name: Student 1"
    Then I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
