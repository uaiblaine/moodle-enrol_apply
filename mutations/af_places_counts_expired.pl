# AF: count expired approvals against the places number - the same ratchet the applicant count
# had, and worse here: under the shipped KEEP an expired row stays ACTIVE for ever, so a course
# whose places filled once could never approve anybody again.
s{'enrolid = :enrolid AND status = :active AND \(timeend = 0 OR timeend > :now\)',\n            \['enrolid' => \(int\) \$instance->id, 'active' => ENROL_USER_ACTIVE, 'now' => time\(\)\]}{'enrolid = :enrolid AND status = :active',\n            ['enrolid' => (int) \$instance->id, 'active' => ENROL_USER_ACTIVE]};
