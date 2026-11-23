# FC5: the decider facet counts decisions by a person, never a key admission's applicant.
s#CASE WHEN \{\$alias\}\.decidedby > 0 AND \{\$alias\}\.decidedby <> \{\$alias\}\.userid THEN#CASE WHEN {\$alias}.decidedby > 0 THEN#;
