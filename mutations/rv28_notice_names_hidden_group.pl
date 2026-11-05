# RV28: the managers' notice names the key group whatever its visibility, judged for no recipient.
s#&& \\enrol_apply\\local\\groupvisibility::visible_to\(\s*\$admission->grouprecord,\s*\(int\) \$recipient->id\s*\) => \$admission->group#&& true => \$admission->group#s;
