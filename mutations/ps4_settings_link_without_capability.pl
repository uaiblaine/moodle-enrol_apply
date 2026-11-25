# PS4: the cards link the course settings for every reader, without moodle/course:update.
s#\\enrol_apply\\local\\groupscope::holds_in_course\('moodle/course:update', \$context\)\n(\s*)\)\);#true\n${1}));#;
