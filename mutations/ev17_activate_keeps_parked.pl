# EV17: activate() leaves a parked approval in place, so its own write fires the stale entry as well.
s#        \\enrol_apply\\local\\decisionlog::clear\(\(int\) \$userenrolment->id\);\n##;
