@enrol @enrol_apply
Feature: Enrolment upon approval
  In order to control who joins my course
  As a teacher
  I need to review and approve enrolment applications before users get access

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
    # The field set has no generator behind it, and which fields are collected is covered
    # exhaustively by tests/local/fields_test.php. One field keeps these scenarios thin.
    And the "C1" apply enrolment method asks for "s_city"

  Scenario: A student applies and gets no course access until the application is approved
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    And I press "Start application"
    Then I should see "Check your details"
    And I set the field "City/town" to "Campinas"
    And I set the field "'City/town' is up to date" to "1"
    And I press "Submit application"
    Then I should see "Application submitted"
    And I am on "Course 1" course homepage
    And I should not see "New section"

  # The only scenario that runs with JavaScript, and it is the one that has to. Two paths exist
  # nowhere else in this feature: the application modal, which is what a browser with JavaScript
  # actually gets, and the queue's bulk bar, whose whole behaviour is JavaScript. The disabled
  # assertions below are the only automated check that enrol_apply/manage runs at all - nothing
  # else in this repository executes the plugin's JavaScript, and a module that only phpcs and
  # eslint have read has been wrong here before.
  @javascript
  Scenario: A teacher approves a pending application and the student gains access
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    Then I should see "Student 1"
    # The bar lives in core's sticky footer now, and the rows, the header checkbox and the bar
    # are one core/checkbox-toggleall group. Selecting through the header is what proves it.
    And I should see "Go" in the "sticky-footer" "region"
    And the "With selected users..." "field" should be disabled
    And I click on "Select all" "checkbox"
    And the "With selected users..." "field" should be enabled
    And I set the field "With selected users..." to "Confirm requests"
    And I press "Go"
    Then I should see "The selected enrolment applications have been updated."
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "New section"

  Scenario: A teacher cancels a pending application and the student stays out
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    And I set the field "Select Student 1" to "1"
    And I set the field "With selected users..." to "Cancel requests"
    And I press "Go"
    Then I should see "The selected enrolment applications have been updated."
    And I should see "Nothing to display"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should not see "New section"

  # No @javascript, and that is the point of this one. The filters have to work as a plain GET
  # form, and this scenario is also the ONLY thing holding manage.php's url threading: the search
  # has to survive the decision round trip, which no unit test can see because a page script's url
  # assembly has no unit to redden. mutations/gates.conf records that absence deliberately.
  Scenario: Searching the queue narrows it, and the search survives a decision
    Given the following "users" exist:
      | username | firstname  | lastname    | email                  |
      | student2 | Zephyrina  | Quillsworth | student2@example.com   |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    Then I should see "Student 1"
    And I should see "Zephyrina Quillsworth"
    # Narrowing to one of the two, with the other as the control on every assertion below.
    When I set the field "Search" to "quillsworth"
    And I press "Apply filters"
    Then I should see "Zephyrina Quillsworth"
    And I should not see "Student 1"
    And I should see "Search quillsworth"
    And I should see "1 of 2 applications"
    # The decision returns through manage.php's own redirect, which is where the search is lost
    # if the url does not carry it.
    When I set the field "Select Zephyrina Quillsworth" to "1"
    And I set the field "With selected users..." to "Confirm requests"
    And I press "Go"
    Then I should see "Search quillsworth"
    And I should see "No application matches the filters applied"
    # And clearing it brings back the application the filter was hiding all along.
    When I follow "Clear all"
    Then I should see "Student 1"
    And I should not see "Search quillsworth"

  # The second decision route: core's own participants page. @javascript is not a choice
  # here and it is the opposite of the queue's bar above - core ships the "With selected
  # users..." select DISABLED and only core/checkbox-toggleall clears it, so without
  # JavaScript Mink sets the value happily and then never posts "formaction" at all,
  # because a disabled field is left out of the submission.
  #
  # One applicant, not two. What this scenario exists to prove is the wiring core owns and
  # nothing else in this repository exercises: the menu, action_redir.php's regex sweep of
  # the checkbox names, the confirmation form and the round trip back. That the selection
  # survives that round trip for MORE than one applicant is a property of the form's hidden
  # bulkuser[] inputs, and tests/bulk/operations_test.php holds it with two.
  @javascript
  Scenario: A teacher confirms an application from the course participants page
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    Then I should see "Student 1"
    And I click on "Select 'Student 1'" "checkbox"
    And I choose "Confirm enrolment applications" from the participants page bulk action menu
    Then I should see "Selected applicants"
    And I press "Confirm enrolment applications"
    Then I should see "Enrolment applications decided: 1"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "New section"

  # The other half of that page, and the half that needs no JavaScript at all: the icon is an
  # ordinary link that no core module claims, so the browser follows it. Without JavaScript
  # the scenario above cannot even open its menu.
  #
  # The click is scoped to the applicant's own row, which is what every core scenario touching
  # this column does, and the review page is then identified by the applicant's name rather
  # than by the page's own furniture - a whole-page click plus "Awaiting a decision" would pass
  # against a link built from the wrong id, since the fixture has exactly one application and
  # any id would land on a page saying the same thing.
  Scenario: A teacher opens one application from the course participants page
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    Then I should see "Student 1"
    And I click on "Decide this application" "link" in the "Student 1" "table_row"
    Then I should see "Awaiting a decision"
    And I should see "Confirm this application"
    And I should see "Student 1" in the "page-header" "region"

  # The one branch this slice adds that changes state, and the only surface it lives on.
  #
  # No @javascript: the review page is a plain form and the confirmation is a plain page, and
  # this is what proves the destructive decision is reachable and refusable without JavaScript.
  # It also pins the thing no unit test can reach - that pressing Cancel does NOT unenrol until
  # the confirmation is answered, which is the whole point of the interception.
  Scenario: Cancelling one application asks first, and backing out changes nothing
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    And I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    And I click on "Decide this application" "link" in the "Student 1" "table_row"
    When I press "Cancel this application"
    Then I should see "Cancel this application?"
    And I should see "Keep the application"
    # Backing out leaves the application exactly where it was.
    When I press "Keep the application"
    Then I should see "Awaiting a decision"
    # And going through with it does unenrol them.
    When I press "Cancel this application"
    And I press "Cancel and unenrol"
    Then I should see "The selected enrolment applications have been updated."
    And I am on the "Course 1" "enrolled users" page
    And I should not see "Student 1"

  # Deferral end to end, and the only place the three halves of it meet. No @javascript, for
  # the same reason the cancellation scenario above has none: the review page is a plain form.
  #
  # Three things no unit test can put together. The note survives the decision and is read back
  # by the next person to open the application - which is the whole reason the column exists.
  # The application stays decidable afterwards, so the reason can be corrected rather than
  # frozen. And the applicant reads about THEIR OWN state on the course page, where every one of
  # the three states used to read as "waiting for a decision".
  Scenario: Deferring one application records why, and the applicant is told what happened
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    And I log in as "teacher1"
    And I am on the "Course 1" "enrolled users" page
    And I click on "Decide this application" "link" in the "Student 1" "table_row"
    When I set the field "Message to the applicant" to "You are third on the list."
    And I set the field "Note for the record" to "Holding for the September intake."
    And I press "Defer this application"
    Then I should see "The selected enrolment applications have been updated."
    # Reopened, the application says it was deferred and says why.
    And I am on the "Course 1" "enrolled users" page
    And I click on "Decide this application" "link" in the "Student 1" "table_row"
    # Scoped to the status row and not a bare "Deferred": the capacity panel on the same page is
    # headed with that very word, so the bare assertion passes whatever the status says.
    Then I should see "Status: Deferred"
    And I should see "Holding for the September intake."
    # The box is NOT pre-filled: the note belongs to the decision being taken, so a second
    # decision cannot inherit the first one's reason by leaving the box alone.
    And the field "Note for the record" matches value ""
    # And the reason can still be corrected, which the old lookup made impossible.
    When I set the field "Note for the record" to "Waiting for the transcript."
    And I press "Defer this application"
    Then I should see "The selected enrolment applications have been updated."
    And I log out
    # The applicant reads their own state, not the pending wording every state used to get.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Your enrolment application has been deferred."
    And I should not see "New section"

  # The bulk bar must not lie about what is selected, and only a browser can hold this. The bar
  # lives in the sticky footer, OUTSIDE the region a refresh replaces, so it survives a page turn,
  # a sort or a filter change with whatever count and whatever enabled state it had - while every
  # checkbox it was counting has just been destroyed. Sorting is the cheapest refresh to provoke:
  # since the table became dynamic, clicking a column heading replaces the region over AJAX
  # instead of reloading the page.
  #
  # @javascript is not a choice here. The count and the reset are the module's whole behaviour,
  # and the non-JavaScript driver executes none of it.
  @javascript
  Scenario: Selecting rows counts them, and a sort puts the count back
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    Then I should see "0 selected on this page"
    And I click on "Select all" "checkbox"
    And I should see "1 selected on this page"
    And the "With selected users..." "field" should be enabled
    # The refresh. The row comes back, and with it a checkbox nobody has ticked.
    And I click on "Application date" "link"
    And I should see "Student 1"
    And I should see "0 selected on this page"
    And the "With selected users..." "field" should be disabled

  # The audit report belongs to the method whose icon opened it. No @javascript: the icons on
  # the enrolment methods page are ordinary links and the report renders server side.
  #
  # This is the one thing no unit test in this repository can hold. report.php's call to
  # scope_to_method() is the whole feature, and it lives in a page script - delete it and every
  # PHPUnit test still passes, because they call that method directly. Here the deletion shows up
  # as the second method's report listing an application that is not its own, which is the defect
  # exactly as it was measured.
  #
  # The second method is added AFTER the application is submitted, deliberately: with two methods
  # on the course the enrolment page renders two panels and "Start application" stops being an
  # unambiguous button. Ordering the fixture this way needs no scoping and no second applicant.
  #
  # Driven as ADMIN and not as teacher1, and that is a fact about the plugin rather than a
  # convenience: the report icon is gated on enrol/apply:viewreports, which is deliberately
  # narrower than manageapplications and which the editingteacher archetype does not carry. A
  # teacher sees the Edit and Manage icons on that row and no report icon at all - measured in a
  # faildump when this scenario was first written against teacher1.
  Scenario: The applications report shows the method whose icon opened it
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "admin"
    And I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name | Second intake |
    And I am on the "Course 1" "enrolment methods" page
    # The method the application was made to lists it.
    And I click on "Enrolment applications" "link" in the "Apply for this course" "table_row"
    Then I should see "Student 1"
    # The other method has none of its own, and must not borrow them.
    And I am on the "Course 1" "enrolment methods" page
    And I click on "Enrolment applications" "link" in the "Second intake" "table_row"
    Then I should see "Nothing to display"
    And I should not see "Student 1"

  # Capability enforcement is covered by tests/lib_test.php: asserting on it here would
  # mean asserting on an exception page, which behat's exception hook fails by design.
