# LK4: an active enrolment through a deciding method no longer locks.
s#\$active = \(int\) \$row->status === ENROL_USER_ACTIVE && \(\$timeend === 0 \|\| \$timeend > \$now\);#\$active = false;#;
