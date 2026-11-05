# RV30: the reviewed mode's every-group-full warning keeps key-only's "enrol" wording.
s#get_string\(\$review \? 'keyreviewallfull' : 'keyonlyallfull', 'enrol_apply'\)#get_string('keyonlyallfull', 'enrol_apply')#;
