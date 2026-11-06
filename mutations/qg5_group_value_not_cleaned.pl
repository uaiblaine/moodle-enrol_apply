# QG5: the group filter's value is not checked against what this reader was offered.
s#return in_array\(\$value, \$offered, true\) \? \$value : null;#return \$value === '' ? null : \$value;#;
