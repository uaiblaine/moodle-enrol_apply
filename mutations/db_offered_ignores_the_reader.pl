# DB: offer whatever the administrator ticked, without intersecting it with what THIS reader may
# see. Ticking a box then hands every operator a control over a profile field their own site
# withholds from them - and a filter is an oracle even when the value is never printed: apply it,
# read the count. The controls look identical to an administrator, who may see everything anyway.
s{        foreach \(self::pool\(\) as \$name\) \{\n            if \(!array_key_exists\(\$name, \$mappings\)\) \{\n                // Offered by the administrator, not visible to this reader in this scope\.\n                continue;\n            \}\n}{        foreach (self::pool() as \$name) \{\n}s;
