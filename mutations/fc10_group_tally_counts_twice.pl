# FC10: a record is counted again for a value it already counted (the application's group also in its joined list).
s#\$key = \$record->recordid \. '\|' \. \$value;\n(\s+)if \(isset\(\$seen\[\$identifier\]\[\$key\]\)\) \{\n\s+continue;\n\s+\}#\$key = \$record->recordid . '|' . \$value;#;
