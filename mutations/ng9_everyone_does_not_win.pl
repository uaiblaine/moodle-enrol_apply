# NG9: "everyone" no longer swallows the other picks.
s#if \(in_array\(self::ALL, \$picked, true\)\) \{\n\s*return self::ALL;\n\s*\}\n##;
