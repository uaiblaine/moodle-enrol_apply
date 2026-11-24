@enrol @enrol_apply @javascript
Feature: The mentee approval queue in its grid view
  In order to decide my mentees' applications as a data report
  As a mentor holding the approval capability in my mentees' own user contexts
  I need the id-less queue's grid to list my mentees' applications alone, with less than the site-wide queue shows, and decide them

  # A thin smoke test of the mentee grid (stage W5, SQ-D13) in a real request: what each reader may read and
  # decide is PHPUnit's (tests/output/mentee_queue_view_test.php, tests/local/wide_queue_test.php,
  # tests/local/wide_decision_post_test.php). The mentor's role is allowed the capability and assigned in
  # Student 1's user context only; Student 2 applied too and is nobody's mentee.
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | mentor1  | Mentor    | 1        | mentor1@example.com  |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "roles" exist:
      | shortname   | name             |
      | applymentor | Applicant mentor |
    And the following "role capabilities" exist:
      | role        | enrol/apply:manageapplications |
      | applymentor | allow                          |
    And the following "role assigns" exist:
      | user    | role        | contextlevel | reference |
      | mentor1 | applymentor | User         | student1  |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name | Apply for this course |
    And I log out
    And the "C1" apply enrolment method asks for ""
    And "student1" has applied to "C1" with the key of ""
    And "student2" has applied to "C1" with the key of ""

  Scenario: A mentor reads their mentee's application on the grid, without identity or site-wide views, and confirms it
    Given I log in as "mentor1"
    When I am on the "enrol_apply > manage applications" page
    Then I should see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should not see "Student 2" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "1 application" in the "[data-region='enrol_apply-total']" "css_element"
    And I should see "Course 1" in the "Student 1" "table_row"
    And I should see "Apply for this course" in the "Student 1" "table_row"
    And I should not see "student1@example.com"
    And I should see "Awaiting decision" in the "[data-region='enrol_apply-tiles']" "css_element"
    And I should not see "Courses with applications" in the "[data-region='enrol_apply-tiles']" "css_element"
    And I should not see "Open the report"
    When I click on "View" "button" in the "[data-region='enrol_apply-bar']" "css_element"
    Then "Cards" "button" should exist in the "[data-region='enrol_apply-bar']" "css_element"
    And "By course" "button" should not exist in the "[data-region='enrol_apply-bar']" "css_element"
    When I click on "Select Student 1" "checkbox"
    And I click on "Confirm" "button" in the "[data-region='enrol_apply-dock']" "css_element"
    Then I should see "Student 1" in the "Confirm 1 application" "dialogue"
    And I set the field "Message to the applicant" to "Welcome to the course."
    And I click on "Confirm 1" "button" in the "Confirm 1 application" "dialogue"
    Then I should see "The selected enrolment applications have been updated."
    And I should not see "Student 1" in the "[data-region='enrol_apply-rows']" "css_element"
    And I should see "No application is awaiting a decision." in the "[data-region='enrol_apply-rows']" "css_element"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "New section"
