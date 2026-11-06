# QG13: a page naming no group is not defaulted to the reader's groups in visible groups mode.
s#\$group = \$raw === null \? \$groupdefault : groupfilter::clean#\$group = \$raw === null ? null : groupfilter::clean#;
