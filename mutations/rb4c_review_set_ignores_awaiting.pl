# RB4C: the reviewable set drops "awaiting a decision", so a decided application gets Review.
s#AND ' \. implode\(' AND ', \$awaiting\);#AND 1 = 1';#;
