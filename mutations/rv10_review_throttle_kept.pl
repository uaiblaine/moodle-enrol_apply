# RV10: a valid key for review leaves the wrong-key count as it was.
s#(// A valid key ends the tries, as an admission does\.\n\s*)\\enrol_apply\\local\\keyattempts::clear\(\(int\) \$instance->id, \$userid\);#${1}#;
