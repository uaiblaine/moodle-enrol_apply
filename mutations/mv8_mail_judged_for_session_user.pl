# MV8: the e-mail is masked for whoever triggered it, not for each recipient.
s/\$visible = \$formatter::visible_keys\(\$coursecontext, \(int\) \$recipient->id\);/\$visible = \$formatter::visible_keys(\$coursecontext);/;
