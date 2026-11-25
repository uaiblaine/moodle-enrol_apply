# KS22: the admission notice judges the group's visibility for the session's user, not the recipient.
s#(visible_to\(\$named->grouprecord, )\$recipientid\);#$1(int) \$GLOBALS['USER']->id);#;
