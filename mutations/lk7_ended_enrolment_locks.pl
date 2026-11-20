# LK7: an active enrolment whose period has ended still locks.
s#ENROL_USER_ACTIVE && \(\$timeend === 0 \|\| \$timeend > \$now\)#ENROL_USER_ACTIVE#;
