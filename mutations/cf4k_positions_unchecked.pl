# CF4K: a position the select does not have is accepted.
s{if \(!\$positions \|\| array_diff\(\$positions, array_keys\(\$options\)\)\) \{}{if (!\$positions) \{}s;
