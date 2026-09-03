# AM: stop escaping the identity values. flexible_table writes a cell's value into the markup
# with no escaping of its own, so this is the plugin's own XSS boundary, not core's. It moved
# from other_cols() into col_fullname() when the identity fields stopped being columns and became
# the second line of the applicant's cell - the boundary moved with them, it did not go away.
s{\$identity\[\] = html_writer::span\(s\(\$value\), 'enrol_apply-identityvalue'\);}{\$identity[] = html_writer::span(\$value, 'enrol_apply-identityvalue');}s;
