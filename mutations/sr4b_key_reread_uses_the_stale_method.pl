# SR4b: the method is not read again once the locks are held, so the key is resolved against the stale one.
s#\$current = \$DB->get_record\('enrol', \['id' => \(int\) \$instance->id\]\);#\$current = \$instance;#;
