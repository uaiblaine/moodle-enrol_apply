# CF3E: any integer from the archive is kept as the description format, and format_text() throws on it.
s#return in_array\(\$format, \$known, true\) \? \$format : \(int\) FORMAT_MOODLE;#return \$format;#s;
