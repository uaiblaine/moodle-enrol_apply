# XA20: judge the capability alone, as before: the course gate that download.php never runs is gone.
s#return groupscope::enters_with\(\$course, 'enrol/apply:viewreports'\);#return has_capability('enrol/apply:viewreports', \$context);#;
