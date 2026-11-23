# QE10: a facet whose value is applied is counted under that value, not without it.
s#\$without = in_array\(\$name, \$applied, true\) \? \[\$name\] : \[\];#\$without = [];#;
