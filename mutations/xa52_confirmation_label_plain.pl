# XA52: the confirmation checkbox takes the plain spelling of the field name into a raw sink.
s#'',\n(\s+)get_string\('confirmfield', 'enrol_apply', fields::label\(\$key, true\)\)#'',\n$1get_string('confirmfield', 'enrol_apply', fields::label(\$key, false))#s;
