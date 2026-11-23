# QG4: an application without a key leaves the cell blank instead of saying it has no group.
# Re-pointed when the cell's content moved to applications::keygroup_html().
s#return s\(get_string\('queuegroupnone', 'enrol_apply'\)\);#return '';#;
