@enrol @enrol_apply
Feature: The applicant chooses a group when applying
  In order to say which group I want to join
  As an applicant
  I need to choose among the groups the enrolment method offers, and be told which one I applied for

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "groups" exist:
      | name      | course | idnumber |
      | Evening A | C1     | EA       |
      | Evening B | C1     | EB       |
      | Evening C | C1     | EC       |
      | Evening D | C1     | ED       |
      | Evening E | C1     | EE       |
      | Evening F | C1     | EF       |
      | Evening G | C1     | EG       |
      | Evening H | C1     | EH       |
      | Evening I | C1     | EI       |
      | Evening J | C1     | EJ       |
      | Évening K | C1     | EK       |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name | Apply for this course |
    And I log out
    # No profile fields: the chooser is the subject here.
    And the "C1" apply enrolment method asks for ""
    And applicants to the "C1" apply method must choose among "Evening A, Evening B, Evening C, Evening D, Evening E, Evening F, Evening G, Evening H, Evening I, Evening J, Évening K"

  # The list itself is JavaScript (amd/src/group_chooser.js), which nothing but Behat executes. The
  # full group needs local_groupdist's seats; without it the seats step skips the scenario.
  @javascript
  Scenario: The list shows groups with places, typing finds a full group disabled, and the choice is named after submitting
    Given the group "Évening K" of "C1" has "0" seats
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I press "Start application"
    And I click on "Group" "field"
    Then I should see "Showing 8 of 10 groups with places. Type to search all 11." in the ".enrol_apply-chooser-list" "css_element"
    And I should see "Evening H" in the ".enrol_apply-chooser-list" "css_element"
    And I should not see "Evening I" in the ".enrol_apply-chooser-list" "css_element"
    # Case and accents are ignored, and the full group comes disabled, under its heading.
    And I type "evening k"
    And I should see "Évening K" in the "li[role='option'][aria-disabled='true']" "css_element"
    And I should see "Full" in the ".enrol_apply-chooser-heading" "css_element"
    And I press the escape key
    And I click on "Group" "field"
    And I click on "Evening B" "text" in the ".enrol_apply-chooser-list" "css_element"
    And I press "Submit application"
    And I should see "Your application for Evening B is waiting for a decision."

  # Without JavaScript the chooser is the plain select it enhances, on apply.php.
  Scenario: Without JavaScript the applicant chooses from the plain list on the application page
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I press "Start application"
    And I set the field "Group" to "Evening B"
    And I press "Submit application"
    Then I should see "Your application for Evening B is waiting for a decision."

  # No @javascript: the chip and the ticks are server markup. Eleven groups are more than the eight
  # a page shows as cards, so only the ticked ones are cards: the choice and the method's own.
  # The ticks are read from the markup: the cards and the "add a group" select both post groups[],
  # which the browserkit driver cannot resolve to one checkbox by label.
  Scenario: The decider reads the chosen group and finds it ticked on top of the method's
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the "C1" apply enrolment method also joins "Evening A"
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I set the field "Group" to "Evening B"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    And I click on "Review" "link" in the "Student 1" "table_row"
    Then I should see "Chose: Evening B"
    And I should not see "Key accepted"
    And "//input[@type='checkbox'][@data-name='Evening B'][@checked]" "xpath_element" should exist
    And "//input[@type='checkbox'][@data-name='Evening A'][@checked]" "xpath_element" should exist
    And "//input[@type='checkbox'][@data-name='Evening C']" "xpath_element" should not exist
    And I should see "Applicant's choice"
