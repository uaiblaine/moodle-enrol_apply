# LP6: confirm_enrolment() honours a choice made through a course role the decider's lapsed enrolment left behind.
s#\\enrol_apply\\local\\groupscope::holds_in_course\(\n(\s*)'enrol/apply:choosedecision',#has_capability(\n$1'enrol/apply:choosedecision',#;
