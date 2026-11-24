# QW25: the review page's lookup joins every record of the user enrolment, not the newest one.
s#\n\s*AND NOT EXISTS \(\n\s*SELECT 1\n\s*FROM \{enrol_apply_submission\} snewer\n\s*WHERE snewer\.userenrolmentid = s\.userenrolmentid\n\s*AND \(snewer\.timecreated > s\.timecreated\n\s*OR \(snewer\.timecreated = s\.timecreated AND snewer\.id > s\.id\)\)\n\s*\)##;
