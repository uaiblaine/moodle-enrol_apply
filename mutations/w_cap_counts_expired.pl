# W: count expired enrolments against the cap again - the ratchet. Under the shipped
# expiredaction of KEEP the row survives forever, so a course whose places filled and then
# expired had applications closed permanently with an empty approval queue.
s{'enrolid = :enrolid AND \(timeend = 0 OR timeend > :now\)',\n            \['enrolid' => \(int\) \$instance->id, 'now' => time\(\)\]}{'enrolid = :enrolid',\n            ['enrolid' => (int) \$instance->id]};
