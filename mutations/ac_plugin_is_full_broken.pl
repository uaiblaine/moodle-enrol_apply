# AC: break the public capacity API on the plugin object. It is the only spelling three
# plugins outside this repo are allowed to use - enrol_apply is an optional dependency for
# each, so they may not name a class in its namespace - and a wrong answer here is a wrong
# answer on their surfaces, not on any of this plugin's own.
s{return \\enrol_apply\\local\\capacity::is_full\(\$instance\);}{return false;};
