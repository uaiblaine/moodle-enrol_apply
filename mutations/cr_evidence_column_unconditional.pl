# CR: draw the evidence column on every scope. The mentee scope resolves no identity context - one
# statement spans courses there - so its mask is the names-only one and every cell of the new
# column is empty. The heading is drawn all the same, so a mentor gets a column that promises the
# evidence and shows none of it, on every row, for ever.
s{        if \(\$this->scope->identitycontext !== null\) \{\n            \$columns\[\] = 'snapshot';\n            \$headers\[\] = get_string\('queuesubmitted', 'enrol_apply'\);\n        \}}{        \$columns[] = 'snapshot';\n        \$headers[] = get_string('queuesubmitted', 'enrol_apply');}s;
