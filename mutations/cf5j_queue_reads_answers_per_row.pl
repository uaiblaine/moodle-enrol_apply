# CF5J: the queue reads each row's answers with a query of its own.
s{foreach \(\$this->snapshots\[\(int\) \(\$row->submissionid \?\? 0\)\] \?\? \[\] as \$entry\)}{foreach (submissionrecord::read_snapshot((int) (\$row->submissionid ?? 0)) as \$entry)}s;
