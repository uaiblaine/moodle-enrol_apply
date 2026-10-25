# KW6: the admission keeps whoever is logged in as the decider.
s#(\\enrol_apply\\local\\submission::STATUS_APPROVED,\n\s*)\$userid,\n(\s*)true\n#${1}(int) \$GLOBALS['USER']->id,\n${2}true\n#;
