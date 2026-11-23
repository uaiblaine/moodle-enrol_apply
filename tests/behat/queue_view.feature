@enrol @enrol_apply @javascript
Feature: The approval queue of one method in its grid view
  In order to read and decide the applications awaiting a decision as a data report
  As a teacher
  I need the queue's grid view to list, scope and search them, keeping what I ask in the address, and to decide a selection

  # Each scenario asserts what only the mounted component draws (rows in its own region, its count,
  # its chips, its tiles, its dock and its sheet), never the shell's placeholder. The grid is the
  # method queue's page (CQ-D5): "manage applications" and "applications queue grid" land on it,
  # "applications queue table" asks for today's table. Its decisions post the table's own form to
  # manage.php (CQ-D4); which applications a decision may reach is PHPUnit's
  # (tests/local/decision_post_test.php), the flow is here.
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

  Scenario: Approving two selected applicants with a message enrols them, and the grid lists the rest
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student3 | Student   | 3        | student3@example.com |
    And "student3" has applied to "C1" with the key of ""
    And I log in as "teacher1"
    When I am on the "C1" "enrol_apply > applications queue grid" page
    Then I should see "3 applications" in the "[data-region='enrol_apply-total']" "css_element"
    # Nothing selected, no dock.
    And "[data-region='enrol_apply-dock']" "css_element" should not exist
    When I click on "Select Student 1" "checkbox"
    And I click on "Select Student 2" "checkbox"
    Then I should see "2 selected on this page" in the "[data-region='enrol_apply-dock']" "css_element"
    When I click on "Confirm" "button" in the "[data-region='enrol_apply-dock']" "css_element"
    Then I should see "Student 1" in the "Confirm 2 applications" "dialogue"
    And I should see "Student 2" in the "Confirm 2 applications" "dialogue"
    And I should not see "Student 3" in the "Confirm 2 applications" "dialogue"
    And I set the field "Message to the applicant" to "Welcome to the course."
    And I click on "Confirm 2" "button" in the "Confirm 2 applications" "dialogue"
    # manage.php decided, and its redirect lands back on the grid with its notification.
    Then I should see "The selected enrolment applications have been updated."
    And I should see "Student 3" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"
    And "[data-region='enrol_apply-dock']" "css_element" should not exist
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "New section"

  Scenario: Cancelling one selected application asks in the sheet, and deferring another keeps it in the queue
    Given I log in as "teacher1"
    And I am on the "C1" "enrol_apply > applications queue grid" page
    When I click on "Select Student 1" "checkbox"
    And I click on "Cancel applications" "button" in the "[data-region='enrol_apply-dock']" "css_element"
    Then I should see "Cancelling removes the enrolment and the applicant's comment." in the "Cancel 1 application" "dialogue"
    # Back posts nothing and keeps the selection.
    And I click on "Back" "button" in the "Cancel 1 application" "dialogue"
    And "Cancel 1 application" "dialogue" should not exist
    And I should see "1 selected on this page" in the "[data-region='enrol_apply-dock']" "css_element"
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
    # The sheet's button is the confirmation.
    When I click on "Cancel applications" "button" in the "[data-region='enrol_apply-dock']" "css_element"
    And I click on "Cancel 1" "button" in the "Cancel 1 application" "dialogue"
    Then I should see "The selected enrolment applications have been updated."
    And I should not see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"
    When I click on "Select Student 2" "checkbox"
    And I click on "Defer" "button" in the "[data-region='enrol_apply-dock']" "css_element"
    Then I should see "Deferred applications stay in this queue until someone decides them." in the "Defer 1 application" "dialogue"
    And I set the field "Note for the record" to "Waiting for the transcript."
    And I click on "Defer 1" "button" in the "Defer 1 application" "dialogue"
    Then I should see "The selected enrolment applications have been updated."
    And I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "Deferred" in the "Student 2" "table_row"

  Scenario: A viewer reads the grid with no selection and no decision
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher2 | Teacher   | 2        | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | C1     | teacher |
    And the following "permission overrides" exist:
      | capability                   | permission | role    | contextlevel | reference |
      | enrol/apply:viewapplications | Allow      | teacher | Course       | C1        |
    And I log in as "teacher2"
    When I am on the "C1" "enrol_apply > applications queue grid" page
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "You can view these applications but not decide them."
    And "Select Student 1" "checkbox" should not exist
    And "Select all on this page" "checkbox" should not exist
    And "[data-region='enrol_apply-dock']" "css_element" should not exist

  Scenario: The reader's own application has no selection box, and the selection does not survive another scope
    Given "teacher1" has applied to "C1" with the key of ""
    And I log in as "teacher1"
    When I am on the "C1" "enrol_apply > applications queue grid" page
    Then I should see "Teacher 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "Yours" in the "Teacher 1" "table_row"
    And "Select Teacher 1" "checkbox" should not exist
    And "Select Student 1" "checkbox" should exist
    # The page's box selects every row of the page that may be selected, and only those.
    When I click on "Select all on this page" "checkbox"
    Then I should see "2 selected on this page" in the "[data-region='enrol_apply-dock']" "css_element"
    # Another scope is another set of rows: nothing out of sight stays selected.
    When I click on "Pending" "button" in the "[data-region='enrol_apply-bar']" "css_element"
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And "[data-region='enrol_apply-dock']" "css_element" should not exist
