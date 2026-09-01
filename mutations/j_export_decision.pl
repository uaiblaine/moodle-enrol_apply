# J: stop exporting the decision at all - the state this change fixes. Now four columns rather
# than three: the decider's note joined them, and it is exported to both subjects for the same
# reason the other three are.
s|\n            \$export->outcomemessage = trim\(\(string\) \$row->outcomemessage\) !== ''\n                \? \$row->outcomemessage\n                : null;\n.*?\n            \$export->decidedgroups = self::group_names\(\(string\) \$row->decidedgroups, \$context\);\n            \$export->decidedrole = self::role_name\(\(int\) \$row->decidedrole\);\n|\n|s;
