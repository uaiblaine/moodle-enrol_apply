# KS13: only the key's own group is offered ticked, so approving as offered drops the method's own
# list, which the key would have joined.
s|                : enrol_get_plugin\('apply'\)->key_route_groups\(\$instance, \(int\) \$group->id\),|                : [(int) \$group->id],|;
