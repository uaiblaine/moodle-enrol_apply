# GK5: the scan is not scoped to the instance's course.
s{get_records\('groups', \['courseid' => \$instance->courseid\], 'id ASC', 'id, enrolmentkey'\)}{get_records('groups', [], 'id ASC', 'id, enrolmentkey')};
