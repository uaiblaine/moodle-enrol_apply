# BG4: never put the group's idnumber in the option text, so a code cannot be searched for.
s|\$label = \$fact->idnumber !== ''|\$label = false|s;
