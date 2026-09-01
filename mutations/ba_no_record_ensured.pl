# BA: drop the record reconstruction from the deferral path. record_outcome_message() and
# record_decision_note() both loop over the rows they find and write nothing when there are
# none, so an application older than the trail takes a decision whose message is stored
# nowhere and mailed nowhere - in silence, with the status looking perfectly correct.
s{\n            // A record to write to; see confirm_enrolment\(\) and submission::ensure\(\)\.\n            \\enrol_apply\\local\\submission::ensure\(\(int\) \$userenrolment->id\);\n}{\n}s;
