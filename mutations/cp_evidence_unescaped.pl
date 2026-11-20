# CP: stop escaping the evidence cell. flexible_table::format_row() writes a cell's value into the
# markup with no escaping of its own, so both halves of every pair - what the applicant typed and
# what an administrator named a custom field - reach the page raw. It is an XSS hole in a screen
# only managers open, which makes it quieter rather than smaller, and it renders identically for
# every value that happens to hold no markup. Repointed when the pills became one line of answers
# (Q1): the escaping moved to the two assignments the line is built from.
s{\$label = s\(\$entry\['label'\]\);\n(\s*)\$value = s\(\$entry\['value'\]\);}{\$label = \$entry['label'];\n$1\$value = \$entry['value'];}s;
