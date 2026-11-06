# PL5: core's profile rule (5.3) is replaced by "yes", so a narrowed moderator is linked to a
# profile core refuses them. The 5.1/5.2 branch (user_can_view_profile) is reached only on those
# cores, which the fast sweep does not run.
s#return \\core\\user::can_view_profile\(\$user, \$course, \$usercontext\);#return true;#;
