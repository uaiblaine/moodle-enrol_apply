# QW9: the method filter takes any positive id, another plugin's method and an unknown id included.
s#return \$DB->record_exists\('enrol', \['id' => \$id, 'enrol' => 'apply'\]\) \? \$id : null;#return \$id;#;
