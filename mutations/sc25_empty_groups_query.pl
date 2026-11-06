# SC25: a reader in no group reaches get_in_or_equal() with an empty array, which throws.
s#if \(!\$groupids\) \{\n(\s*)return \['1 = 0', \[\]\];#if (false) {\n${1}return ['1 = 0', []];#;
