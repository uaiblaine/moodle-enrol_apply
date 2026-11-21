# HD6: to the resolution a deferral is still open, per row.
s#return !\(\$settled && \(int\) \$record->timedecided > 0\);#return (int) \$record->timedecided === 0;#;
