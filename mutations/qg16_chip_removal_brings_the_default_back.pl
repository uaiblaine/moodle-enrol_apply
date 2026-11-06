# QG16: removing the group chip drops the parameter, which the page reads as "apply the default".
s#\$removeparams\[\\enrol_apply\\local\\groupfilter::NAME\] = \\enrol_apply\\local\\groupfilter::ANY;#unset(\$removeparams[\\enrol_apply\\local\\groupfilter::NAME]);#;
