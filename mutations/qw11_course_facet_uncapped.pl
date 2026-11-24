# QW11: the course and category facets send every option, whatever FACET_LIMIT says.
s#\$kept = \$name === 'method' \? \$ids : array_slice\(\$ids, 0, static::FACET_LIMIT\);#\$kept = \$ids;#;
