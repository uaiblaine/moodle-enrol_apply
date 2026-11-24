# QW19: grouped yes selects the applications without a group, and no the ones with one.
s#return \$value === self::GROUPED_NO \? \$none : \"NOT \(\{\$none\}\)\";#return \$value === self::GROUPED_YES ? \$none : \"NOT ({\$none})\";#;
