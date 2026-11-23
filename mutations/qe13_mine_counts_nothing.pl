# QE13: "My groups" is counted as nothing, though choosing it lists the reader's groups.
s#\$sum \+= \(int\) \(\$counts\[\(int\) \$groupid\] \?\? 0\);#\$sum += 0;#;
