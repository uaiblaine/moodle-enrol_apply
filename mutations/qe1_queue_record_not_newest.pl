# QE1: the queue joins every record of a user enrolment, not its newest: two records are two rows.
s#(LEFT JOIN \{enrol_apply_submission\} s ON s\.userenrolmentid = ue\.id)\n\s*AND NOT EXISTS \(\n\s*SELECT 1\n\s*FROM \{enrol_apply_submission\} snewer\n.*?\n\s*\)\n#$1\n#s;
