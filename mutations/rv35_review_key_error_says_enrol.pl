# RV35: the form's key error for an empty key bypasses the mode's wording.
s#return \$mandatory \? \$this->key_required_message\(\) : null;#return \$mandatory ? get_string('groupkeyrequired', 'enrol_apply') : null;#;
