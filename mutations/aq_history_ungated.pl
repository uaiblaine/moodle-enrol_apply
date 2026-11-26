# AQ: drop the viewreports gate on the earlier-applications panel, handing every editing teacher
# the disclosure the separate capability exists to withhold.
s#        if \(!\\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:viewreports', \$coursecontext\)\) \{\n            return \['hashistory' => false, 'history' => \[\]\];\n        \}\n\n##s;
