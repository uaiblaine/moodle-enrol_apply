# PL5: core's profile rule is replaced by "yes", so a narrowed moderator is linked to a profile
# core refuses them.
s#return \\core\\user::can_view_profile\(\$user, \$course, \$usercontext\);#return true;#;
