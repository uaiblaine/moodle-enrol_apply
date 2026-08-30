# T: stash the profile offer BEFORE the refusal branch, so a refused application leaves an
# offer in the session to update a profile for an application that does not exist.
s{\n        \\enrol_apply\\local\\offer::stash\(\$instance, \$USER, \(array\) \$data\);\n}{\n}s;
s{(\n        \$result = \$this->get_plugin\(\)->submit_application\(\$instance, \$USER->id, \$data\);\n)}{$1\n        \\enrol_apply\\local\\offer::stash(\$instance, \$USER, (array) \$data);\n}s;
