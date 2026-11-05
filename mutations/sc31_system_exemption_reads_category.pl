# SC31: the system exemption reads the course's category context instead of the system one.
s#has_capability\('enrol/apply:manageapplications', context_system::instance\(\), \$userid\)#has_capability('enrol/apply:manageapplications', context_course::instance(\$courseid)->get_parent_context(), \$userid)#;
