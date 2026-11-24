# QW15: the batched applicants count expired rows, which applicants() leaves out.
s#WHERE enrolid \{\$insql\} AND \(timeend = 0 OR timeend > :now\)#WHERE enrolid {\$insql}#;
