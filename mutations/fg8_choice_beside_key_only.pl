# FG8: a stored choice is read before a key-only mode, which the key wins at the door.
s#\n\s*if \(groupkey::mandatory\(\$instance\)\) \{\n\s*return self::KEY;\n\s*\}##;
