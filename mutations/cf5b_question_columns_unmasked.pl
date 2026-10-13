# CF5B: the course report offers every question's column and filter whatever the reader may see.
s{static fn\(string \$key\): bool => \$visible === formatter::ALL_FIELDS \|\| in_array\(\$key, \$visible, true\)}{static fn(string \$key): bool => true}s;
