# GM1: the course settings are linked for every reader, without moodle/course:update.
s#\$canupdate = has_capability\('moodle/course:update', \$context\);#\$canupdate = true;#;
