# GM1: the course settings are linked for every reader, without moodle/course:update.
s#\$canupdate = \\enrol_apply\\local\\groupscope::holds_in_course\('moodle/course:update', \$context\);#\$canupdate = true;#;
