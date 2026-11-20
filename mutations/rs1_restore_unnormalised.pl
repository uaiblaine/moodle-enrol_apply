# RS1: write the archive's option values without normalising them.
s{instanceoptions::set\(\$enrolid, \(array\) instanceoptions::normalise\(\$data\)\);}{instanceoptions::set(\$enrolid, array_intersect_key((array) \$data, (array) instanceoptions::defaults()));};
