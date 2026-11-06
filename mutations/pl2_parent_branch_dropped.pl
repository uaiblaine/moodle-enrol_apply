# PL2: core's "parent" branch is never taken, so a mentor with viewdetails in the applicant's own
# context is refused the profile user/view.php opens for them.
s#\$DB->record_exists\('role_assignments', \['userid' => \$USER->id, 'contextid' => \$usercontext->id\]\)#false#;
