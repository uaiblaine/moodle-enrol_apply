# AE: drop the status clause from places_taken(), which makes PLACES a second spelling of
# APPLICANTS - a pending or deferred application would start occupying a place, and the gap
# between the two numbers that makes overbooking expressible disappears.
s{'enrolid = :enrolid AND status = :active AND \(timeend = 0 OR timeend > :now\)',\n            \['enrolid' => \(int\) \$instance->id, 'active' => ENROL_USER_ACTIVE, 'now' => time\(\)\]}{'enrolid = :enrolid AND (timeend = 0 OR timeend > :now)',\n            ['enrolid' => (int) \$instance->id, 'now' => time()]};
