# V: drop the places cap from the WRITE door. Re-anchored when the predicate moved into
# \enrol_apply\local\capacity - before that extraction the cap lived inline in three places
# and deleting any one of them reddened nothing at all.
s{\n            if \(\\enrol_apply\\local\\capacity::is_full\(\$instance\)\) \{.*?\n            \}\n}{\n}s;
