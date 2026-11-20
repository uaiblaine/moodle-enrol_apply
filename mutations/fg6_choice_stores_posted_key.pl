# FG6: a choice stores the posted key mode as it is, a key-only one included.
s#\x27customchar1\x27 => \$key === groupkey::MODE_ANY \? groupkey::MODE_ANY : groupkey::MODE_OFF,#\x27customchar1\x27 => \$key,#;
