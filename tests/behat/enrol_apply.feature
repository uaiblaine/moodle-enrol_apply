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

  # With JavaScript, because two paths exist only there: the application modal, which is what a
  # browser with JavaScript gets, and the queue's bulk bar, which starts disabled only because
  # enrol_apply/manage disables it on init. Only the @javascript scenarios execute the plugin's
  # JavaScript at all.
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
    # The bar lives in core's sticky footer, and the rows, the header checkbox and the bar are
    # one core/checkbox-toggleall group. Selecting through the header is what proves it.
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

  # No @javascript: the filters have to work as a plain GET form. This is also the only test of
  # manage.php's url threading - the search has to survive the decision's redirect, and a page
  # script's url assembly has no unit test.
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

  # The second decision route: core's own participants page. It needs @javascript, unlike the
  # queue's bar: core ships the "With selected users..." select disabled and only
  # core/checkbox-toggleall enables it, so without JavaScript Mink sets the value and then never
  # posts "formaction", because a disabled field is left out of the submission.
  #
  # One applicant, not two. This proves the wiring core owns and nothing else here exercises:
  # the menu, action_redir.php's regex sweep of the checkbox names, the confirmation form and
  # the round trip back. That the selection survives the round trip for more than one applicant
  # depends on the form's hidden bulkuser[] inputs, and tests/bulk/operations_test.php holds it
  # with two.
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

  # The cancel confirmation, which exists only on the review page.
  #
  # No @javascript: the review page is a plain form and the confirmation is a plain page, and
  # this proves the destructive decision is reachable and refusable without JavaScript. It also
  # pins what no unit test can reach: pressing Cancel does not unenrol until the confirmation is
  # answered.
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

  # Deferral end to end. No @javascript, for the same reason as the cancellation scenario above:
  # the review page is a plain form.
  #
  # It puts together three things no unit test can: the note survives the decision and is shown
  # to the next person who opens the application; the application stays decidable, so the reason
  # can be corrected; and the applicant is told their own state on the course page.
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
    # The box is not pre-filled: the note belongs to the decision being taken, so a second
    # decision cannot inherit the first one's reason by leaving the box alone.
    And the field "Note for the record" matches value ""
    # And the reason can still be corrected.
    When I set the field "Note for the record" to "Waiting for the transcript."
    And I press "Defer this application"
    Then I should see "The selected enrolment applications have been updated."
    And I log out
    # The applicant is told their own state, not the pending wording.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Your enrolment application has been deferred."
    And I should not see "New section"

  # The bulk bar must not report a stale selection. It lives in the sticky footer, outside the
  # region a refresh replaces, so a page turn, a sort or a filter change leaves its count and
  # enabled state as they were while every checkbox it counted is destroyed. Sorting is the
  # cheapest refresh to provoke: the table is a dynamic one, so a column heading replaces the
  # region over AJAX instead of reloading the page.
  #
  # It needs @javascript: the count and the reset are the module's own behaviour.
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

  # The as-you-type half. The debounce, the refresh, the chip and the count are all the module's,
  # so it needs @javascript; with scripting off the queue narrows through a page load, which the
  # non-JavaScript search scenario above covers.
  #
  # It also pins how the two interact: a filter change replaces the table region and every
  # checkbox in it, while the bulk bar outside that region must stop claiming the lost selection.
  @javascript
  Scenario: The queue narrows as the operator types, and the selection does not survive it
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
    Then I should see "2 of 2 applications"
    # A selection the operator is about to lose, and the bar that must stop claiming it.
    And I click on "Select all" "checkbox"
    And I should see "2 selected on this page"
    # Narrowing without a page load: the module applies it, the chip is its own work.
    When I set the field "Search" to "quillsworth"
    Then I should see "Zephyrina Quillsworth"
    And I should not see "Student 1"
    And I should see "1 of 2 applications"
    And I should see "Search quillsworth"
    And I should see "0 selected on this page"
    And the "With selected users..." "field" should be disabled
    # And removing it puts the queue back, chip and all, still without a page load.
    When I click on "Remove the filter Search: quillsworth" "link"
    Then I should see "Student 1"
    And I should see "2 of 2 applications"
    And I should not see "Search quillsworth"

  # The configurable per-field filters, driven through the GET form with no JavaScript. This is
  # the only test of manage.php's filter parameter reading, since a page script has no unit test.
  # It presses the button rather than navigating to a hand-built url, so the parameters are the
  # ones the form really sends, and it applies a field filter and a date together because
  # different code reads them.
  Scenario: An administrator chooses which fields the queue may be filtered by
    Given the following config values are set as admin:
      | showuseridentity | institution |
    And the following "users" exist:
      | username | firstname | lastname    | email                | institution |
      | student2 | Zephyrina | Quillsworth | student2@example.com | Ouropretoville |
    And the following config values are set as admin:
      | queuefilterfields | institution | enrol_apply |
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
    Then I should see "2 of 2 applications"
    # The control the administrator enabled is on the page, named as the site names the field.
    And I should see "Institution"
    # Narrowing through the form, which is what carries the parameter into manage.php.
    When I set the field "Institution" to "Ouropretoville"
    And I press "Apply filters"
    Then I should see "Zephyrina Quillsworth"
    And I should not see "Student 1"
    And I should see "1 of 2 applications"
    # A date bound too, read by a different path from the field filter.
    When I set the field "Applied from" to "2020-01-01"
    And I press "Apply filters"
    Then I should see "Zephyrina Quillsworth"
    And I should see "1 of 2 applications"
    # And the whole thing comes back, which is what proves the narrowing was the filters.
    When I follow "Clear all"
    Then I should see "Student 1"
    And I should see "2 of 2 applications"

  # The two halves of the applied-date chip must spell the date the same way. The server renders
  # it from PHP and the module redraws it from the input's own value, and no date format is shared
  # by both (Moodle's comes from the language pack, the browser's from the operating system), so
  # neither side formats it. It needs @javascript: without it only the server's half renders.
  @javascript
  Scenario: The applied-date chip reads the same after a refresh as it did on load
    Given the following "users" exist:
      | username | firstname | lastname    | email                |
      | student2 | Zephyrina | Quillsworth | student2@example.com |
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
    And I set the field "Applied from" to "2020-01-01"
    And I press "Apply filters"
    # The server's spelling, from a real page load. Asserted against the control's own value
    # rather than a literal - see the step's own docblock for why the driver makes that necessary.
    Then the applied-date chip should read what its control holds
    And I should see "2 of 2 applications"
    # And the module's, from a refresh driven by a different control entirely.
    When I set the field "Search" to "quillsworth"
    Then I should see "Zephyrina Quillsworth"
    And I should see "1 of 2 applications"
    And the applied-date chip should read what its control holds

  # A field the site does not name in showuseridentity is offered to nobody, whatever the plugin
  # setting says. The plugin setting decides what the queue may offer; what the reader can
  # already see decides what it does offer.
  Scenario: A field the site withholds is offered to nobody
    Given the following config values are set as admin:
      | showuseridentity | institution |
    And the following config values are set as admin:
      | queuefilterfields | institution,city | enrol_apply |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I press "Start application"
    And I press "Submit application"
    And I log out
    When I log in as "teacher1"
    And I am on the "C1" "enrol_apply > manage applications" page
    # The control: the field the site DOES name is offered, so an absent control below is about
    # the withheld field and not about a filter row that failed to render.
    Then I should see "Institution"
    And I should not see "City/town"

  # The audit report belongs to the method whose icon opened it. No @javascript: the icons on
  # the enrolment methods page are ordinary links and the report renders server side.
  #
  # report.php's call to scope_to_method() is the whole feature and lives in a page script, so no
  # PHPUnit test reaches it (they call the method directly). Without it the second method's report
  # lists an application that is not its own.
  #
  # The second method is added after the application is submitted: with two methods on the course
  # the enrolment page renders two panels and "Start application" is no longer an unambiguous
  # button. Ordering the fixture this way needs no scoping and no second applicant.
  #
  # Driven as admin rather than teacher1: the report icon is gated on enrol/apply:viewreports,
  # which is narrower than manageapplications and which the editingteacher archetype does not
  # carry, so a teacher sees no report icon on that row.
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
