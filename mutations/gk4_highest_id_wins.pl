# GK4: groups scanned newest first, so a shared key admits into the highest id.
s{\['courseid' => \$instance->courseid\], 'id ASC', 'id, enrolmentkey'}{['courseid' => \$instance->courseid], 'id DESC', 'id, enrolmentkey'};
