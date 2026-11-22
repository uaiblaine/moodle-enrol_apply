# FC6: the group facet tallies a record under its application group alone, the joined list ignored.
s#\$groups = array_map\('intval', explode\(',', \(string\) \$record->joined\)\);#\$groups = [];#;
