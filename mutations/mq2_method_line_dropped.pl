# MQ2: the course cell of the spanning queues loses the method's name, so two methods of one course
# cannot be told apart in the rows (MQ-D15).
s#\$method = \$this->methods\[\(int\) \$row->enrolid\] \?\? null;\n        if \(\$method === null\) \{\n            return \$cell;#\$method = null;\n        if (\$method === null) {\n            return \$cell;#s;
