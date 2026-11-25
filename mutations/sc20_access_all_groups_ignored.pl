# SC20: moodle/site:accessallgroups no longer lifts the narrowing.
s#return !self::holds_in_course\('moodle/site:accessallgroups', \$context, \$userid\);#return true;#;
