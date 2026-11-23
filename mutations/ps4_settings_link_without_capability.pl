# PS4: the cards link the course settings for every reader, without moodle/course:update.
s#has_capability\('moodle/course:update', \$context\)\n(\s*)\)\);#true\n${1}));#;
