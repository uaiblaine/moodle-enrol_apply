# F4E: drop the correlation from the groups orphan sweep. NOT EXISTS over the whole of {enrol} is
# false whenever any enrol instance exists, so no orphan is ever deleted.
s# WHERE e\.id = \{enrol_apply_groups\}\.enrolid##s;
