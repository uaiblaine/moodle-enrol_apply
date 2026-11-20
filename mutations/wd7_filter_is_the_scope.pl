# WD7: the queue's group filter is built on the scope's predicate, which drops the all-groups methods.
s#return groupscope::group_where\(\[\(int\) \$value\]\);#return groupscope::where([(int) \$value]);#;
