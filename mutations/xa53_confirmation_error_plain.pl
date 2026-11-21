# XA53: the confirmation checkbox's error takes the plain spelling of the field name into a raw sink.
s#= get_string\('confirmfield', 'enrol_apply', fields::label\(\$key, true\)\);#= get_string('confirmfield', 'enrol_apply', fields::label(\$key, false));#;
