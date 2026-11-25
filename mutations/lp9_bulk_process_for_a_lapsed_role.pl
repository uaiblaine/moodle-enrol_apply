# LP9: the bulk operation's process() lets a lapsed course role through.
s#\\enrol_apply\\local\\groupscope::holds_in_course\('enrol/apply:manageapplications', \$manager->get_context\(\)\)#has_capability('enrol/apply:manageapplications', \$manager->get_context())#;
