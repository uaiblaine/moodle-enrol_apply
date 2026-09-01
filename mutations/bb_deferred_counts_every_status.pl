# BB: drop the status clause from the deferred count, making it a second spelling of
# applicants(). The number is reported to a manager beside that one, so a subset that is not
# a subset produces "3 held, 3 of them deferred" on a screen where one row is approved.
s{'enrolid = :enrolid AND status = :waiting AND \(timeend = 0 OR timeend > :now\)',\n            \['enrolid' => \(int\) \$instance->id, 'waiting' => ENROL_APPLY_USER_WAIT, 'now' => time\(\)\]}{'enrolid = :enrolid AND (timeend = 0 OR timeend > :now)',\n            ['enrolid' => (int) \$instance->id, 'now' => time()]};
