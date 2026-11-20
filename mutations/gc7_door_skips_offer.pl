# GC7: any group id is taken for an offered one with a place.
s#\$offered = \$groupid === null \? \[\] : static::offered\(\$instance, \$userid\);#\$offered = \$groupid === null ? [] : [\$groupid => (object) ['full' => false]];#;
