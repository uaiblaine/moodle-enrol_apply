# EV11: assign_decided_role() reports no role, so the approval event says none was given.
s#(role_assign\(\$roleid, \$userid, context_course::instance\(\$instance->courseid\)->id, 'enrol_apply', \$instance->id\);\n\n        return )\$roleid;#${1}0;#;
