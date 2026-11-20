# DC3: a method the post carries no message for is keyed by its own id rather than falling back to
# the plain message's group, so its rows look up an entry that does not exist.
s#\$key = array_key_exists\(\$enrolid, \$permethod\) \? \$enrolid : 0;#\$key = \$enrolid;#;
