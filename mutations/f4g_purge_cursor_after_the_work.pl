# F4G: advance the retention sweep's cursor after purge_row() instead of before it. A failing row
# then never moves the cursor, and when no later row carries it past, the next iteration selects
# the failing row again until the time budget runs out.
s#\n( *)\$lastid = \(int\) \$row->id;\n( *)try \{\n( *)\$this->purge_row\(\(int\) \$row->id\);\n#\n$2try {\n$3\$this->purge_row((int) \$row->id);\n$3\$lastid = (int) \$row->id;\n#s;
