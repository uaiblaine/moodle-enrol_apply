# RV11: the managers' notice of an application for review does not name its key group.
s#\$forreview = \$keyoutcome === \\enrol_apply\\local\\submission::KEY_FOR_REVIEW \? \$keygroupid : 0;#\$forreview = 0;#;
