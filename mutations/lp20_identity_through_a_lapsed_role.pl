# LP20: the review page's applicant card judges the identity fields in the course for a lapsed reader too.
s#\\enrol_apply\\local\\groupscope::judging_context\(\$coursecontext\),\n(\s*)\(int\) \$applicant->id#\$coursecontext,\n${1}(int) \$applicant->id#;
