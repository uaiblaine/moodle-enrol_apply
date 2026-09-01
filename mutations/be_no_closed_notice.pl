# BE: stop telling the manager the applicant limit is reached. That state can hold an EMPTY
# queue - every held application deferred, and a deferred row is freed by nothing - so without
# it the course refuses everybody with no screen able to say why.
s{\n        \$closednotice = '';\n        if \(\$instance !== null && \\enrol_apply\\local\\capacity::applications_closed\(\$instance\)\) \{.*?\n        \}\n}{\n        \$closednotice = '';\n}s;
