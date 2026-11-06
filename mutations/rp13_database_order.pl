# RP13: the groups keep the database's order, which puts "Turma 10" before "Turma 2" (RP-D22).
s#\n        \\core_collator::asort_objects_by_property\(\$coursegroups, 'name', \\core_collator::SORT_NATURAL\);##;
