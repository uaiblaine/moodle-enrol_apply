# NG10: a restore from another site drops the group marker with the ids.
s#return self::parse\(\$stored\)->group \? self::GROUP : '';#return '';#;
