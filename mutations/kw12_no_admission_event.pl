# KW12: the admission raises no event.
s#(\\enrol_apply\\event\\application_admitted_by_key::create\(\[.*?\]\))->trigger\(\);#\$unused = \1;#s;
