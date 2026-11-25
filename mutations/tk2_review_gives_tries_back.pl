# TK2: an application a valid key sends for review clears the applicant's wrong-key count again.
s#(\$this->apply\(\$instance, \$userid, \$data, \$keygroupid, \\enrol_apply\\local\\submission::KEY_FOR_REVIEW\);)#$1\n        \\cache::make('enrol_apply', 'keyattempts')->delete((int) \$instance->courseid . '_' . \$userid);#;
