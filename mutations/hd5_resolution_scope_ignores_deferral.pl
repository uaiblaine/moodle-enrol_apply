# HD5: to the resolution a deferral is still open, in SQL.
s#return "NOT \(\{\$alias\}\.status IN \(\{\$settled\}\) AND \{\$alias\}\.timedecided > 0\)";#return "{\$alias}.timedecided = 0";#;
