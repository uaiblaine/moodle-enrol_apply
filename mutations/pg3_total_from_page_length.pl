# PG3: the total is taken as the page's length.
s#\$total = \(int\) \$record->\{\$countalias\};#\$total = count(\$ids);#;
