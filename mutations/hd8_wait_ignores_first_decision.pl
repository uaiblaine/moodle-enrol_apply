# HD8: the wait to the first response reads the first decision.
s#\$end = \$measure === .first. && \$first > 0 \? \$first : \(int\) \$record->timedecided;#\$end = (int) \$record->timedecided;#;
