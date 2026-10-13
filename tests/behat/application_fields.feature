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

  # No @javascript: the condition editor is two plain form posts, and this is the only test that
  # loads condition.php as a page (a missing formslib require reached the owner as a Whoops while
  # every unit test, which builds the form directly, stayed green).
  Scenario: A teacher makes a question depend on an earlier one
    Given the "C1" apply method asks a select "Shift" of "Morning,Night"
    And the "C1" apply method asks a "text" "Night permit number"
    And I log in as "teacher1"
    And I am on the "C1" "enrol_apply > application fields" page
    # By its own label: core's editor above lists the same question with an Edit link of its own.
    When I click on "//a[@aria-label='Edit the display condition of Night permit number']" "xpath_element"
    Then I should see "Display condition" in the "page-header" "region"
    And I set the field "Show this question only when this earlier question" to "Shift"
    And I set the field "Night" to "1"
    And I press "Save changes"
    Then I should see "The display condition was saved."
    And I should see "Shown when the answer to \"Shift\" is one of: Night"

  # @javascript: the answers of the chosen question appear with no reload (core's hideIf).
  @javascript
  Scenario: Choosing the controlling question shows its answers at once
    Given the "C1" apply method asks a select "Shift" of "Morning,Night"
    And the "C1" apply method asks a "text" "Night permit number"
    And I log in as "teacher1"
    And I am on the "C1" "enrol_apply > application fields" page
    And I click on "//a[@aria-label='Edit the display condition of Night permit number']" "xpath_element"
    And "Night" "checkbox" should not be visible
    When I set the field "Show this question only when this earlier question" to "Shift"
    Then "Night" "checkbox" should be visible
    And I set the field "Night" to "1"
    And I press "Save changes"
    And I should see "Shown when the answer to \"Shift\" is one of: Night"

  Scenario: Adding the method can lead straight to its questions
    Given I log in as "teacher1"
    And I am on the "Course 1" "enrolment methods" page
    And I select "Course enrol confirmation" from the "Add method" singleselect
    And I set the field "Custom instance name" to "Second intake"
    When I press "Save and edit application fields"
    Then I should see "Application fields" in the "region-main" "region"
    And I should see "Display conditions"

  # Needs the fleet's customfield_pattern type, which this plugin's CI does not install; run it
  # where the type is mounted. @javascript: the mask is a browser behaviour, and the application
  # form is a modal whose HTML and JavaScript arrive through a fragment after the page loaded.
  @javascript @customfield_pattern
  Scenario: A masked question formats what the applicant types in the application modal
    Given the "C1" apply method asks a "cpf" pattern "CPF"
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I press "Start application"
    And I click on "CPF" "field"
    And I type "ab52998224725"
    Then the field "CPF" matches value "529.982.247-25"
