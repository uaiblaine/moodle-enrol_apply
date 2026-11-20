# GD15: the chosen chip names the group to a reader with no course capability (a mentor).
s#\n            \|\| !self::reads_course_groups\(\$coursecontext\)\n(            \|\| !\\enrol_apply\\local\\groupvisibility::visible_to\(\$group, \(int\) \$USER->id\)\n        \) \{\n            return \['chip' => get_string\('chosenchipunnamed')#\n$1#;
