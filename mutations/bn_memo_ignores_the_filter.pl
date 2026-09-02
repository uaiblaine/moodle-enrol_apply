# BN: memoise whatever manager asks, filter or no filter. The menu is then tidy and the DISPATCH
# is broken: action_redir.php and enrol/renderer.php both ask with a FILTERED manager, and the
# second decision of a session would find no operation to run.
s#        if \(empty\(\$manager->get_enrolment_filter\(\)\)\) \{\n            if \(\$this->bulkmenuoffered\) \{\n                return \[\];\n            \}\n            \$this->bulkmenuoffered = true;\n        \}#        if (\$this->bulkmenuoffered) {\n            return [];\n        }\n        \$this->bulkmenuoffered = true;#s;
