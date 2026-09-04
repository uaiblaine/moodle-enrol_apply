# DE: forget that the field, date and course filters narrow anything. scope_total() then
# short-circuits and the count line reads "N of N" on a filtered queue, and
# print_nothing_to_display() falls through to core's generic message instead of the plugin's
# filtered-empty one. Every row stays correct.
#
# REPOINTED 2026-09-04 when is_narrowed() grew the course and category clauses. The previous
# pattern named the five-clause body and stopped matching the moment a sixth arrived - the same
# way it happened to AD, CD and CH. The full --dry-run is what catches it, in the change that
# causes it rather than three slices later.
s{        return \$this->search !== ''\n            \|\| \$this->status !== null\n            \|\| \$this->fieldfilters !== \[\]\n            \|\| \$this->appliedfrom !== null\n            \|\| \$this->appliedto !== null\n            \|\| \$this->categoryfilter !== null\n            \|\| \$this->coursefilter !== null;}{        return \$this->search !== '' || \$this->status !== null;}s;
