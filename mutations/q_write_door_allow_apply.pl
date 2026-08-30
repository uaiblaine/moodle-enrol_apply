# Q: delete the eligibility predicate from the WRITE door, leaving allow_apply() reachable
# only from enrol_page_hook() and the form's access check - which is the defect this guard
# was added to close.
s{\n            if \(\$this->allow_apply\(\$instance, \(int\) \$userid\) !== true\) \{\n                return false;\n            \}\n}{\n}s;
