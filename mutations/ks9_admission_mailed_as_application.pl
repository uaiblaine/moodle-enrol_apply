# KS9: an admission is mailed to the managers as an application waiting for them, with a link to a
# queue that holds nothing for it.
s|\$keyoutcome === \\enrol_apply\\local\\submission::KEY_ADMITTED \? \$keygroupid : 0|0|;
