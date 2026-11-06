# RP14: an option no longer carries the group's idnumber, so the code is not searchable (RP-D21).
s#        \$label = \$fact->idnumber !== ''\n#        \$label = false\n#;
