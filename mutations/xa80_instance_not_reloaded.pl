# XA80: submit_application() judges the method object the caller passed, not the row as it is now.
s#\$instance = \$DB->get_record\('enrol', \['id' => \(int\) \$instance->id, 'enrol' => 'apply'\], '\*', MUST_EXIST\);#\$instance = \$instance;#;
