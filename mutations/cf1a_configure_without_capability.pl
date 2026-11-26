# CF1A: anybody who can reach an apply instance's handler configures its questions.
s#return \$context !== null && \\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:config', \$context\);#return \$context !== null;#;
