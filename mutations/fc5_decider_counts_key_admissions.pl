# FC5: the decider facet counts decisions by a person, never a key admission's applicant.
s#\$person = \$facet === 'decider' \? " AND \{\$alias\}\.decidedby > 0 AND \{\$alias\}\.decidedby <> \{\$alias\}\.userid" : '';#\$person = \$facet === 'decider' ? " AND {\$alias}.decidedby > 0" : '';#;
