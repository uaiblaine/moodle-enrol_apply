# F3E: the site default applicant limit goes back to PARAM_INT, which stores a negative as typed.
s#('enrol_apply/maxenrolled',.*?)'/\^\[0-9\]\+\$/'#${1}PARAM_INT#s;
