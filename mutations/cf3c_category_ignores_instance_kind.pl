# CF3C: the category restore trusts the parent enrol id, so a manual instance receives categories.
s{(public function process_enrol_apply_appcategory\(\$data\) \{\n\s*\$data = \(object\) \$data;\n\n\s*)\$enrolid = \$this->get_apply_instanceid\(\);}{$1\$enrolid = (int) \$this->get_new_parentid('enrol');}s;
