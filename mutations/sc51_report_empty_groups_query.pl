# SC51: a report reader in no group reaches get_in_or_equal() with an empty array, which throws.
s#(\$groupids = array_values\(array_map\('intval', \$groupids\)\);\n\s*)if \(!\$groupids\) \{#${1}if (false) {#;
