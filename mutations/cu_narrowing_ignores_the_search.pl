# CU: forget that a SEARCH narrows anything. is_narrowed() then answers for the other filters only,
# so a searched queue reports the FILTERED count as its scope total - "4 of 4 applications" beside a
# capacity header saying 4 awaiting decision on a method holding 312 - and an empty result gets
# core's generic "Nothing to display" instead of the plugin's filtered-empty message. Every row is
# still correct, which is what makes it invisible.
#
# Repointed in the slice that made is_narrowed() answer for five filters rather than two; the
# previous pattern matched the whole two-clause body and stopped applying the moment it grew.
s{        return \$this->search !== ''\n            \|\| \$this->status !== null\n}{        return \$this->status !== null\n            \|\| false\n}s;
