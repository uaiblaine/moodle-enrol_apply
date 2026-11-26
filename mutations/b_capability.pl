# B: delete the icon's capability gate.
s{\n        if \(!\\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:manageapplications', \$manager->get_context\(\)\)\) \{\n            return \$actions;\n        \}\n}{\n}s;
