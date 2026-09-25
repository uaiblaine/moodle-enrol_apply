# F1C: count every enrolment, active or not, when choosing whom to tell about a new application.
# A teacher whose own enrolment is suspended or has ended keeps the role, so they are mailed a
# link to a queue that require_login() refuses them.
s{get_enrolled_users\(\$context, 'enrol/apply:manageapplications', 0, 'u\.\*', null, 0, 0, true\);}{get_enrolled_users(\$context, 'enrol/apply:manageapplications');};
