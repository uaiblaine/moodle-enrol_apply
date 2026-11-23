# QW23: the group filter and its facet are offered to every site-wide reader, not only one who may see hidden groups.
s#return coursefilter::offered\(\$this->scope\)\n\s*&& has_capability\('moodle/course:viewhiddengroups', \\context_system::instance\(\)\);#return true;#;
