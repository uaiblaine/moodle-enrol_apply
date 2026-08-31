# AC: break the public capacity API on the plugin object. It is the only spelling three plugins
# outside this repo may use - enrol_apply is an optional dependency for each - and a wrong answer
# here is wrong on THEIR surfaces, not on any of this plugin's own. Note it keeps the name
# is_full() while delegating to applications_closed(): renaming it breaks them silently, because
# is_callable() would simply start returning false and each would fall back to its old count.
s{return \\enrol_apply\\local\\capacity::applications_closed\(\$instance\);}{return false;};
