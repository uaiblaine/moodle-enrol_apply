# RV29: the reviewed mode's no-keyed-group warning keeps key-only's "enrol" wording.
s#get_string\(\$review \? 'keyreviewnokeys' : 'keyonlynokeys', 'enrol_apply'\)#get_string('keyonlynokeys', 'enrol_apply')#;
