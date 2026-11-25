# SF9: a user list's delete in a user context ignores whether the list names the context's user.
s/if \(in_array\(\(int\) \$context->instanceid, array_map\(.intval., \$userlist->get_userids\(\)\), true\)\)/if (true)/;
