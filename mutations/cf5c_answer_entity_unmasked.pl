# CF5C: the datasource withholds the snapshot and the shared questions, but not the answer entity.
s{if \(in_array\(\$identifier, \$withheld, true\) \|\| str_starts_with\(\$identifier, \$prefix\)\)}{if (in_array(\$identifier, \$withheld, true))}s;
