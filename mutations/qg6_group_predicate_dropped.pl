# QG6: the group filter is read and chipped but never reaches the listing's SQL.
s#(groupfilter::where\(\$this->scope, \$this->groupfilter\);\n)\s*\$wheres\[\] = \$groupwhere;\n#$1#;
