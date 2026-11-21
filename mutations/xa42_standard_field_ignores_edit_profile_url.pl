# XA42: a standard field stays editable for a user whose profile is edited elsewhere.
s#return \$elsewhere \|\| self::is_locked\(#return self::is_locked(#;
