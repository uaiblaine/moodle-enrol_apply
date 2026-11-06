# RV18: every key outcome joins its key group on an approval without a choice, not only for review.
s#\$key->outcome === \\enrol_apply\\local\\submission::KEY_FOR_REVIEW && \$key->groupid > 0#\$key->groupid > 0#;
