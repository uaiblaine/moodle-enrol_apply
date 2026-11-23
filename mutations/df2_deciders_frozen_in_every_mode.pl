# DF2: "Who decides" is frozen in separate groups too, where its answer is the teacher's.
s#(\$mform->addHelpButton\('deciders', 'deciders', 'enrol_apply'\);)#$1\n        \$mform->hardFreeze('deciders');#;
