# AY: drop the course-capability gate on the capacity panel, showing a mentor - who holds nothing
# in the course - the method's configured limits and its enrolment counts.
s#        if \(!has_capability\('enrol/apply:manageapplications', \$coursecontext\)\) \{\n            return \['hascapacity' => false\];\n        \}\n\n##s;
