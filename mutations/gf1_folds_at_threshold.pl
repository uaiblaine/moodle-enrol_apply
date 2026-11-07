# GF1: the keyed-group list folds at the threshold rather than past it.
s#\$fold = count\(\$groups\) > \\enrol_apply\\local\\groupfilter::THRESHOLD;#\$fold = count(\$groups) >= \\enrol_apply\\local\\groupfilter::THRESHOLD;#;
