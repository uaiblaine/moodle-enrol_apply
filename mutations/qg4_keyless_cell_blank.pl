# QG4: an application without a key leaves the cell blank instead of saying it has no group.
s#return \$label \. s\(get_string\('queuegroupnone', 'enrol_apply'\)\);#return '';#;
