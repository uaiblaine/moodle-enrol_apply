# MK8: erasing one user keeps their approval memberships.
s/\n\s*\\core_group\\privacy\\provider::delete_groups_for_user\(\$contextlist, \x27enrol_apply\x27\);//;
