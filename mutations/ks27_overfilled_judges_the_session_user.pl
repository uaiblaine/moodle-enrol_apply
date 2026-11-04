# KS27: the overfilled-group warning judges the group's visibility for the session's user, not the reader.
s#groupvisibility::visible_to\(\$over, \$readerid\)#groupvisibility::visible_to(\$over, (int) \$GLOBALS['USER']->id)#;
