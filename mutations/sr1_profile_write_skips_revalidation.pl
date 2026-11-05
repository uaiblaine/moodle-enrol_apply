# SR1: the accepted offer writes a custom field without asking its type again.
s#\$field !== null && !customfield::errors\(\$field, \(int\) \$user->id, \$change\[.values.\]\)#\$field !== null#;
