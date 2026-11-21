# FD5B: the restore does not write the first decision.
s#return \[.timefirstdecided. => \(int\) \(\$data->timefirstdecided \?\? 0\)\];#return [];#;
