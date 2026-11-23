# QE15: the applied-before filter applies for a reader who may not be told of earlier applications.
s#\$this->appliedbefore = \$this->history\n\s*&& #\$this->appliedbefore = #;
