# SW4: the per-row check reads the system grant directly again, so a switched administrator
# decides an application outside the moderator's groups.
s#\\enrol_apply\\local\\groupscope::system_exempt\(\s*\(int\) \$courseid,\s*\(int\) \$USER->id,\s*\\enrol_apply\\local\\groupscope::DECIDE\s*\)#has_capability('enrol/apply:manageapplications', context_system::instance())#;
