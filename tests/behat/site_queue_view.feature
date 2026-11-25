@enrol @enrol_apply @javascript
Feature: The site-wide approval queue in its grid view
  In order to read and decide the applications awaiting a decision across the site as a data report
  As a site-wide reader of the approval queue
  I need the id-less queue's grid to list every course's applications, filter them by course, and decide a selection across enrolment methods

  # Each scenario asserts what only the mounted component draws (rows in its own region, its count, its
  # chips, its dock and its sheet). The id-less page is the site-wide queue's grid for a site-wide reader
  # (stage W3, SQ-D12); "applications queue table" asks for today's table, whose own dialog
  # enrol_apply.feature keeps covered. Which applications a decision may reach is PHPUnit's
  # (tests/local/wide_decision_post_test.php); the flow is here.
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
      | Course 2 | C2        | topics |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name | Apply for this course |
    And I add "Course enrol confirmation" enrolment method in "Course 2" with:
      | Custom instance name | Moderator recruitment |
    And I log out
    And the "C1" apply enrolment method asks for ""
    And the "C2" apply enrolment method asks for ""
    And "student1" has applied to "C1" with the key of ""
    And "student2" has applied to "C2" with the key of ""

  Scenario: The id-less page is the grid, with every course's applicants and the course and method of each
    Given I log in as "admin"
    When I am on the "enrol_apply > manage applications" page
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
    And I should see "Course 1" in the "Student 1" "table_row"
    And I should see "Apply for this course" in the "Student 1" "table_row"
    And I should see "Course 2" in the "Student 2" "table_row"
    And I should see "Moderator recruitment" in the "Student 2" "table_row"
    And I should see "Courses with applications" in the "[data-region='enrol_apply-tiles']" "css_element"
    # The header names the site; the page's own title is the content heading, once.
    And I should see "Acceptance test site" in the "#page-header h1" "css_element"
    And I should not see "Confirm enrolments" in the "#page-header h1" "css_element"
    When I follow "Table view"
    Then I should see "Student 1"
    And "[data-region='enrol_apply-rows']" "css_element" should not exist
    When I follow "Grid view"
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"

  Scenario: A selection across enrolment methods is decided method by method on the grid
    Given I log in as "admin"
    And I am on the "enrol_apply > applications queue grid" page
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
    When I click on "Select all on this page" "checkbox"
    Then I should see "2 selected on this page" in the "[data-region='enrol_apply-dock']" "css_element"
    And I should see "in 2 enrolment methods" in the "[data-region='enrol_apply-dock']" "css_element"
    When I click on "Confirm" "button" in the "[data-region='enrol_apply-dock']" "css_element"
    Then I should see "This selection spans 2 enrolment methods." in the "Confirm 2 applications" "dialogue"
    And I should see "Apply for this course" in the "Confirm 2 applications" "dialogue"
    And I should see "Moderator recruitment" in the "Confirm 2 applications" "dialogue"
    And I should see "Student 2" in the "Confirm 2 applications" "dialogue"
    And I set the field "Message to these applicants" to "Welcome to the course."
    And I click on "Confirm 2" "button" in the "Confirm 2 applications" "dialogue"
    Then I should see "The selected enrolment applications have been updated."
    And I should not see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "No application is awaiting a decision." in the "[data-region='enrol_apply-rows']" "css_element"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "New section"
    And I log out
    And I log in as "student2"
    And I am on "Course 2" course homepage
    And I should see "New section"

  Scenario: The course filter narrows the grid to one course, and the address keeps it
    Given I log in as "admin"
    And I am on the "enrol_apply > applications queue grid" page
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
    When I press "Filters"
    And I press "Course 1 1"
    And I press "Show 1"
    Then I should see "Course 1" in the "[data-region='enrol_apply-chips']" "css_element"
    And I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"
    # The course is the address's course parameter: the same address, loaded again, lands filtered.
    When I reload the page
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"

  Scenario: A site-wide viewer reads the grid with no selection and no decision
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | viewer1  | Site      | Viewer   | viewer1@example.com |
    And the following "roles" exist:
      | shortname   | name               |
      | queueviewer | Application viewer |
    And the following "role capabilities" exist:
      | role        | enrol/apply:viewapplications |
      | queueviewer | allow                        |
    And the following "system role assigns" exist:
      | user    | role        |
      | viewer1 | queueviewer |
    And I log in as "viewer1"
    When I am on the "enrol_apply > manage applications" page
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "You can view these applications but not decide them."
    And "Select Student 1" "checkbox" should not exist
    And "Select all on this page" "checkbox" should not exist
    And "[data-region='enrol_apply-dock']" "css_element" should not exist

  Scenario: The By course view lists each enrolment method with its counts and leads to its queue
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student3 | Student   | 3        | student3@example.com |
    And "student3" has applied to "C1" with the key of ""
    And I log in as "admin"
    And I am on the "enrol_apply > applications queue grid" page
    And I should see "3 applications" in the "[data-region='enrol_apply-total']" "css_element"
    When I click on "View" "button" in the "[data-region='enrol_apply-bar']" "css_element"
    And I click on "By course" "button" in the "[data-region='enrol_apply-bar']" "css_element"
    Then I should see "2 enrolment methods" in the "[data-region='enrol_apply-total']" "css_element"
    And I should see "Apply for this course" in the "Course 1" "table_row"
    And I should see "Moderator recruitment" in the "Course 2" "table_row"
    And I should see "2" in the "//tr[contains(., 'Apply for this course')]//*[@data-figure='awaiting']" "xpath_element"
    And I should see "1" in the "//tr[contains(., 'Moderator recruitment')]//*[@data-figure='awaiting']" "xpath_element"
    And I should see "0" in the "//tr[contains(., 'Apply for this course')]//*[@data-figure='deferred']" "xpath_element"
    And "Select all on this page" "checkbox" should not exist
    And "Columns" "button" should not be visible
    # The view is the address's show=methods: the same address, loaded again, lands on it.
    When I reload the page
    Then I should see "2 enrolment methods" in the "[data-region='enrol_apply-total']" "css_element"
    And I should see "Moderator recruitment" in the "Course 2" "table_row"
    When I click on "Open the queue" "link" in the "Course 1" "table_row"
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "Student 3" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "2 applications" in the "[data-region='enrol_apply-total']" "css_element"
