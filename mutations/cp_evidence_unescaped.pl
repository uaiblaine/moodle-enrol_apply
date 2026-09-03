# CP: stop escaping the evidence cell. flexible_table::format_row() writes a cell's value into the
# markup with no escaping of its own, so both halves of every pair - what the applicant typed and
# what an administrator named a custom field - reach the page raw. It is an XSS hole in a screen
# only managers open, which makes it quieter rather than smaller, and it renders identically for
# every value that happens to hold no markup.
s{html_writer::span\(s\(\$entry\['label'\]\), 'enrol_apply-fieldname'\) \. ' ' \. s\(\$entry\['value'\]\),}{html_writer::span(\$entry['label'], 'enrol_apply-fieldname') . ' ' . \$entry['value'],}s;
