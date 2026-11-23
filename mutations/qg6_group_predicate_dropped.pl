# QG6: the group filter is read and chipped but never reaches the listing's SQL.
# Re-pointed when the stage-1 headless queue split the listing's narrowing into one entry per filter.
s#\n\s*\$narrowing\[groupfilter::NAME\] = groupfilter::where\(\$this->scope, \$this->groupfilter\);##;
