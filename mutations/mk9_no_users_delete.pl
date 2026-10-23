# MK9: erasing a userlist keeps their approval memberships.
s/\n\s*\\core_group\\privacy\\provider::delete_groups_for_users\(\$userlist, \x27enrol_apply\x27\);//;
