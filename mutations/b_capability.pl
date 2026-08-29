# B: delete the icon's capability gate.
s{\n        if \(!has_capability\('enrol/apply:manageapplications', \$manager->get_context\(\)\)\) \{\n            return \$actions;\n        \}\n}{\n}s;
