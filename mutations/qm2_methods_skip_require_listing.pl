# QM2: the "By course" view reads the listing without require_listing(), leaving the refusal to its site-wide condition.
s#\$listing = self::require_listing\(0\);(\n\s*if \(!coursefilter::offered\(\$listing\)\))#\$listing = queue::listing_scope(0);$1#;
