# QW14: the batched places count every status, so a pending or deferred row takes a place.
s#WHERE enrolid \{\$insql\} AND status = :active AND \(timeend = 0 OR timeend > :now\)#WHERE enrolid {\$insql} AND (timeend = 0 OR timeend > :now)#;
