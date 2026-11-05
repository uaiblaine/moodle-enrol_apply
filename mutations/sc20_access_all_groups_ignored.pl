# SC20: moodle/site:accessallgroups no longer lifts the narrowing.
s#return !has_capability\('moodle/site:accessallgroups', \$context, \$userid\);#return true;#;
