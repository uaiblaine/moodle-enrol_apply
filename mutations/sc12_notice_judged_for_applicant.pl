# SC12: the notice's recipient filter judges the applicant whose request sends it, not the recipient.
s#groupscope::allows_group\(\(int\) \$instance->courseid, \$keygroupid, \(int\) \$user->id\)#groupscope::allows_group((int) \$instance->courseid, \$keygroupid, (int) \$userid)#;
