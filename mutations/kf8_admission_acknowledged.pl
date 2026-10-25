# KF8: an admission is sent to the acknowledgement page, whose "approved" claims a review.
s#return \(string\) new moodle_url\('/course/view.php', \['id' => \$instance->courseid\]\);#return (string) new moodle_url('/enrol/apply/applied.php', ['instance' => \$instance->id]);#;
