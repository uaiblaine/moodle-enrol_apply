# KS9: an admission is mailed to the managers as an application waiting for them, with a link to a
# queue that holds nothing for it.
s#\$data,\n(\s*)\$admitted \? \$keygroupid : 0,#\$data,\n${1}0,#;
