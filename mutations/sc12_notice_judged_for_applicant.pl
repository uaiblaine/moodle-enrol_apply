# SC12: the notice's recipient filter judges the applicant whose request sends it, not the recipient.
s#(\s+)\$keygroupid,\n(\s+)\(int\) \$user->id,\n(\s+)\\enrol_apply\\local\\groupscope::DECIDE,#$1\$keygroupid,\n$2(int) \$userid,\n$3\\enrol_apply\\local\\groupscope::DECIDE,#;
