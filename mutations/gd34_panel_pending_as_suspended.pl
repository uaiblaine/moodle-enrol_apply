# GD34: "Already in this course" reads an application still waiting as a suspended enrolment.
s#\n\s*return \[get_string\('outcomeawaiting', 'enrol_apply'\), 'bg-warning text-dark'\];#\n            return [get_string('participationsuspended', 'enrol'), 'bg-secondary text-dark'];#;
