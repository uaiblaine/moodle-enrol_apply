@enrol @enrol_apply
Feature: The Groups section of an enrolment method's settings
  In order to let applicants choose their group and keep moderators from deciding on each other
  As a teacher
  I need to say how an applicant's group is decided and who decides the applications

  # No JavaScript: the section's answers show and hide their fields with core's hideIf, which a
  # browser without JavaScript leaves all on screen, so every field can be set in one table.
  Background:
    Given the following "courses" exist:
      | fullname | shortname | format | groupmode |
      | Course 1 | C1        | topics | 1         |
    And the following "groups" exist:
      | name        | course | idnumber |
      | Evening A   | C1     | EA       |
      | Evening B   | C1     | EB       |
      | Moderadores | C1     | MOD      |
    And the following "groupings" exist:
      | name     | course | idnumber |
      | Evenings | C1     | EVE      |
    And the following "grouping groups" exist:
      | grouping | group |
      | EVE      | EA    |
      | EVE      | EB    |
    And I enable "apply" "enrol" plugin
    And I log in as "admin"

  Scenario: Applicants choose among a grouping's groups, and only those who see every group decide
    When I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | Custom instance name                     | Apply as a moderator                 |
      | The applicant chooses from a list        | 1                                    |
      | Add the groups of a grouping             | Evenings                             |
      | The applicant must choose                | Yes: no application without a group |
      | Group seats apply to this method         | No: ignore seats                     |
      | Who decides applications to this method? | Only those who see every group       |
    And I am on the "Course 1" "enrolment methods" page
    And I click on "Edit" "link" in the "Apply as a moderator" "table_row"
    Then I should see "Applicants choose among 2 groups"
    And I should see "Decided only by those who see every group"
    And the field "The applicant chooses from a list" matches value "1"
    And the field "The applicant must choose" matches value "Yes: no application without a group"
    And the field "Group seats apply to this method" matches value "No: ignore seats"
    And the field "Who decides applications to this method?" matches value "Only those who see every group"

  Scenario: An applicant who must choose needs a group to choose from
    When I add "Course enrol confirmation" enrolment method in "Course 1" with:
      | The applicant chooses from a list | 1                                    |
      | The applicant must choose         | Yes: no application without a group |
    Then I should see "Offer at least one group: the applicant must choose one."
