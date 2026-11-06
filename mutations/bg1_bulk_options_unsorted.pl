# BG1: list the groups in the database's order instead of natural order. "Turma 10" would then
# precede "Turma 2" in every chooser, the bulk form's among them (RP-D22).
s|        \\core_collator::asort_objects_by_property\(\$coursegroups, 'name', \\core_collator::SORT_NATURAL\);\n||s;
