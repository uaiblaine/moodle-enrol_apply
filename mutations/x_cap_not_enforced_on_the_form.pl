# X: drop the applicant cap from the form's access check - the door BOTH transports run.
s{\n        if \(\\enrol_apply\\local\\capacity::applications_closed\(\$instance\)\) \{\n            throw new \\moodle_exception\('maxenrolledreached', 'enrol_apply'\);\n        \}\n}{\n}s;
