# GK3: both sides trimmed before comparing, so keys differing only by a space collide.
s{hash_equals\(\(string\) \(\$group->enrolmentkey \?\? ''\), \$key\)}{hash_equals(trim((string) (\$group->enrolmentkey ?? '')), trim(\$key))};
