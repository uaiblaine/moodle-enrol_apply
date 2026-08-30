# X: drop the cap from the form's access check, the door the modal and apply.php both run.
s{\n        if \(\\enrol_apply\\local\\capacity::is_full\(\$instance\)\) \{\n            throw new \\moodle_exception\('maxenrolledreached', 'enrol_apply'\);\n        \}\n}{\n}s;
