# DK: accept a malformed applied date as a filter. day_bounds() then returns null for it and
# build_sql() emits no predicate, so the queue calls itself narrowed, draws a chip naming a date
# nothing can match, and rewrites its own url around it - while listing everything.
s{        // Validated by the same helper that turns it into a boundary, so the two cannot disagree\.\n        return queuefilter::day_bounds\(\$value, null\)\[0\] === null \? null : \$value;}{return \$value;}s;
