# H: restore the empty-array return that crashed the caller.
# Every $ on BOTH sides is escaped: perl interpolates the replacement too, which silently
# ate $row on the first attempt and produced "->decidedgroups".
s|\n        \$ids = array_values\(array_filter\(array_map\('intval', explode\(',', \(string\) \$row->decidedgroups\)\)\)\);\n\n        return \$ids \?: null;\n|\n        return array_values(array_filter(array_map('intval', explode(',', (string) \$row->decidedgroups))));\n|s;
