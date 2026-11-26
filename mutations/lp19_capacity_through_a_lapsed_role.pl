# LP19: the review page shows the method's capacity through a lapsed course role.
s#if \(!\\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:manageapplications', \$coursecontext\)\) \{\n(\s*)return \['hascapacity' => false\];#if (!has_capability('enrol/apply:manageapplications', \$coursecontext)) {\n${1}return ['hascapacity' => false];#;
