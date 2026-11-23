# CR: draw the evidence column on every scope. The mentee scope resolves no identity context - one
# statement spans courses there - so its mask is the names-only one and every cell of the new
# column is empty. The heading is drawn all the same, so a mentor gets a column that promises the
# evidence and shows none of it, on every row, for ever.
# Repointed in W1, when the predicate became applications::has_details_column(), read by the column and the engine's catalogue alike.
s#return \$this->scope->identitycontext !== null;#return true;#;
