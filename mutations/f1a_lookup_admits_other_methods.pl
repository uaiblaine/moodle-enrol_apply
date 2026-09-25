# F1A: drop the enrol-method half of the decision lookup. A suspended user enrolment of any
# other method then passes get_pending_user_enrolment(), and the MUST_EXIST instance lookup
# after it throws half way through a batch whose earlier rows are already decided.
s{WHERE ue\.id = :ueid AND e\.enrol = :enrol AND " \. implode\(' AND ', \$wheres\),}{WHERE ue.id = :ueid AND " . implode(' AND ', \$wheres),};
