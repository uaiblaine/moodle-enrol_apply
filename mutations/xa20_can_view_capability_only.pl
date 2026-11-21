# XA20: judge the capability alone, as before: the course gate that download.php never runs is gone.
s#return can_access_course\(\$course, null, 'enrol/apply:viewreports', true\);#return has_capability('enrol/apply:viewreports', \$context);#;
