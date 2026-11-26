# TK1: an admission clears the applicant's wrong-key count again, so a holder of one valid key gets a fresh budget.
s#(\n\s*)(return \\enrol_apply\\local\\application_result::created\(\\enrol_apply\\local\\submission::KEY_ADMITTED, \$keygroupid\);)#$1\\cache::make('enrol_apply', 'keyattempts')->delete((int) \$instance->courseid . '_' . \$userid);$1$2#;
