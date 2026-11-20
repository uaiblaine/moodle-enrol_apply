# RV17: an application for review approved without a group choice joins the instance's list alone.
s#\$routegroup = \$key->outcome === \\enrol_apply\\local\\submission::KEY_FOR_REVIEW \? \$key->groupid : 0;#\$routegroup = 0;#;
