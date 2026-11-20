# DC1: group the posted ids by the first method the post names a message for, instead of by the
# method {user_enrolments} says each row belongs to. Every row then carries one method's message.
s#\$enrolid = isset\(\$methodof\[\$id\]\) \? \(int\) \$methodof\[\$id\] : 0;#\$enrolid = (int) array_key_first(\$permethod);#;
