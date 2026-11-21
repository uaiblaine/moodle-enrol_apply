# XA3: a wrong key in validation() is counted under the instance, not the course.
s#keyattempts::record_failure\(\(int\) \$this->get_instance\(\)->courseid, \(int\) \$USER->id\);\n(\s*\}\n\s*\}\n\s*/\* A locked key-only)#keyattempts::record_failure((int) \$this->get_instance()->id, (int) \$USER->id);\n$1#;
