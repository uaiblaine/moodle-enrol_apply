# EV15: the observer fires a parked approval without re-reading the row, so an entry whose write never happened logs.
s#\$status === false \|\| \(int\) \$status !== ENROL_USER_ACTIVE#false#;
