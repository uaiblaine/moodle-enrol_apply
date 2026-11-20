# FG2: the grouping's groups do not count towards the offer validation sees.
s#\x27groupoffergrouping\x27 => isset\(\$errors\[\x27groupoffergrouping\x27\]\) \? 0 : \$grouping,#\x27groupoffergrouping\x27 => 0,#;
