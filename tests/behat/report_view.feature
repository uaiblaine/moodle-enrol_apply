@enrol @enrol_apply @javascript
Feature: The applications report in its own view
  In order to read the applications of a course and act on the open ones
  As a reader of the course report
  I need the report's view to list, search, narrow and arrange its rows

  # The view mounts on Moodle 5.3 only (R4), so every scenario starts with core's version step and
  # the 5.1 and 5.2 legs skip it. Each scenario asserts what only the mounted component draws (rows
  # in its own region, its count, its chips), never the shell's placeholder. Driven as admin: the
  # report is a manager's capability by archetype, and admin may also review an open application.
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name | Apply for this course |
    And I log out
    And the "C1" apply enrolment method asks for ""

  Scenario: The rows load, and the search by name narrows them and is removed by its chip
    Given the site is running Moodle version 5.3 or higher
    And "student1" has applied to "C1" with the key of ""
    And "student2" has applied to "C1" with the key of ""
    And I log in as "admin"
    When I am on the "C1" "enrol_apply > applications report" page
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
    And I should see "Turnaround" in the "[data-region='enrol_apply-band']" "css_element"
    When I press "Search by name"
    And I set the field "Search by name" to "Student 2"
    Then I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"
    And I should see "Name: Student 2" in the "[data-region='enrol_apply-chips']" "css_element"
    When I press "Remove filter Name: Student 2"
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"

  Scenario: The columns menu adds a column and removes it again
    Given the site is running Moodle version 5.3 or higher
    And "student1" has applied to "C1" with the key of ""
    And I log in as "admin"
    And I am on the "C1" "enrol_apply > applications report" page
    And I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    # The admission route is the one catalogue column outside the default set.
    And I should not see "Admission route" in the "[data-region='enrol_apply-rows']" "css_element"
    When I press "Columns"
    And I press "Admission route"
    Then I should see "Admission route" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    When I press "Admission route"
    Then I should not see "Admission route" in the "[data-region='enrol_apply-rows']" "css_element"
    # The choice is the reader's preference, read by the server on the next visit.
    And I reload the page
    And I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Admission route" in the "[data-region='enrol_apply-rows']" "css_element"

  Scenario: A filter narrows the rows, shows a chip, and the chip clears it
    Given the site is running Moodle version 5.3 or higher
    And "student1" has applied to "C1" with the key of ""
    And I log in as "admin"
    And I am on the "C1" "enrol_apply > applications report" page
    And I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    When I press "Filters"
    And I press "Approved"
    And I press "Show results"
    Then I should see "Status: Approved" in the "[data-region='enrol_apply-chips']" "css_element"
    And I should see "No application matches" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    When I press "Remove filter Status: Approved"
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"

  Scenario: An open application carries a Review button to its review page
    Given the site is running Moodle version 5.3 or higher
    And "student1" has applied to "C1" with the key of ""
    And I log in as "admin"
    And I am on the "C1" "enrol_apply > applications report" page
    When I click on "Review" "link" in the "Student 1" "table_row"
    Then I should see "Awaiting a decision for"

  Scenario: The table view link reaches core's table and leads back
    Given the site is running Moodle version 5.3 or higher
    And "student1" has applied to "C1" with the key of ""
    And I log in as "admin"
    And I am on the "C1" "enrol_apply > applications report" page
    And I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    When I follow "Table view"
    Then I should see "Student 1" in the "table.reportbuilder-table" "css_element"
    And "[data-region='enrol_apply-rows']" "css_element" should not exist
    When I follow "Applications view"
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
