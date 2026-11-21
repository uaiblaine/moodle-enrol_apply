# RB5: the wait of an undecided record is no longer measured to now.
s#ELSE :\{\$now\} - \{\$alias\}\.timecreated END#ELSE 0 END#;
