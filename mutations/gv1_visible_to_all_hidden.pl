# GV1: a group visible to all is withheld.
s#if \(\$visibility === GROUPS_VISIBILITY_ALL\) \{\n            return true;#if (\$visibility === GROUPS_VISIBILITY_ALL) \{\n            return false;#;
