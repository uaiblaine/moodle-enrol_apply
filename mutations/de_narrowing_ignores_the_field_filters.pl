# DE: forget that the field and date filters narrow anything. scope_total() then short-circuits and
# the count line reads "N of N" on a filtered queue, and print_nothing_to_display() falls through to
# core's generic message instead of the plugin's filtered-empty one. Every row stays correct.
s{        return \$this->search !== ''\n            \|\| \$this->status !== null\n            \|\| \$this->fieldfilters !== \[\]\n            \|\| \$this->appliedfrom !== null\n            \|\| \$this->appliedto !== null;}{        return \$this->search !== '' || \$this->status !== null;}s;
