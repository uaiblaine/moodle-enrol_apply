@enrol @enrol_apply
Feature: Application with a group key, sent for review
  In order to review only the applicants who hold a group's key
  As a teacher
  I need a reviewed key-only method to queue a valid key for a decision, and to let nobody apply without one

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
    And the following "groups" exist:
      | name    | course | idnumber | enrolmentkey |
      | Turma B | C1     | TB       | Turma-B-2027 |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    # No instance name: an unnamed method is called after what it does.
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | What a group enrolment key does | Key only, reviewed: a valid key sends the application for review, and nobody can apply without one |
    And I log out
    # The field set has no generator behind it: without this the method asks for the default fields.
    And the "C1" apply enrolment method asks for ""

  # @javascript: the modal's footer takes its label from the form, and the queue's bulk bar needs it.
  @javascript
  Scenario: A valid key sends the application for review, and approving it joins the key's group
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Application with a group key"
    And I should see "This course needs the group enrolment key your teacher gave you."
    When I press "Apply with a key"
    Then "Submit application" "button" in the "Application with a group key" "dialogue" should not be visible
    And I set the field "Group enrolment key" to "Turma-B-2027"
    And I press "Continue"
    And I should see "Your application for Turma B will be reviewed."
    And I should not see "You will join Turma B."
    And "Submit application" "button" in the "Application with a group key" "dialogue" should be visible
    And I click on "Submit application" "button" in the "Application with a group key" "dialogue"
    And I should see "Your key for Turma B was accepted. Your application is waiting for review"
    And I should not see "you have joined Turma B"
    And I am on "Course 1" course homepage
    And I should not see "New section"
    And I log out
    And I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    And I should see "Student 1"
    And I should see "Key accepted, sent for review"
    And I click on "Select all" "checkbox"
    And I set the field "With selected users..." to "Confirm requests"
    And I press "Go"
    And I should see "The selected enrolment applications have been updated."
    And I am on the "C1" "groups" page logged in as "teacher1"
    And I set the field "groups" to "Turma B (1)"
    And the "members" select box should contain "Student 1 (student1@example.com)"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "New section"

  # No @javascript: the page transport, where Continue is the form's own button.
  Scenario: Without a key nothing is written, and a wrong key offers no way round it
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I press "Apply with a key"
    And I press "Continue"
    Then I should see "Enter your group enrolment key to apply."
    And I should not see "Enter your group enrolment key to enrol."
    And I set the field "Group enrolment key" to "Turma-Z-2027"
    And I press "Continue"
    And I should see "This key is not valid for this course. Check it and try again."
    And I should not see "Send my application for review without the key"
    And I log out
    And I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    And I should not see "Student 1"
