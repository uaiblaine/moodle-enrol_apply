# KS22: the admission notice judges the group's visibility for the session's user, not the recipient.
s#(\$admission->grouprecord,\n                        )\(int\) \$recipient->id#$1(int) \$GLOBALS['USER']->id#;
