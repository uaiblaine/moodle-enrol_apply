# QG15: the unfiltered page's own urls drop `all`, so the next page turn brings the default back.
s#\} else if \(\$this->groupdefault !== null\) \{\n\s*\$params\[groupfilter::NAME\] = groupfilter::ANY;\n\s*\}#}#;
