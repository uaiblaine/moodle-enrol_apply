# QE14: the named filter's key and chosen kinds swapped: "Group key" lists the chosen groups.
s#self::NAMED_KEY => 1,\n(\s*)self::NAMED_CHOSEN => 2,#self::NAMED_KEY => 2,\n$1self::NAMED_CHOSEN => 1,#;
