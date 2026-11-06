# QG12: eight groups already turn the select into a search.
s#\$groupsearch = \$groupcount > \\enrol_apply\\local\\groupfilter::THRESHOLD;#\$groupsearch = \$groupcount >= \\enrol_apply\\local\\groupfilter::THRESHOLD;#;
