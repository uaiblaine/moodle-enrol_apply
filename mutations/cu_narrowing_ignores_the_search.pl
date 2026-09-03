# CU: forget that a search narrows anything. is_narrowed() then answers only for the status, so a
# searched queue reports the FILTERED count as its scope total - "4 of 4 applications" beside a
# capacity header saying 4 awaiting decision on a method holding 312. Every row is still correct,
# which is what makes it invisible: the rows are the thing tests usually assert.
s{        return \$this->search !== '' \|\| \$this->status !== null;}{        return \$this->status !== null;}s;
