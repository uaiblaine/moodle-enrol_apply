# F3F: the site default number of places goes back to PARAM_INT, which stores a negative as typed.
s#('enrol_apply/places',.*?)'/\^\[0-9\]\+\$/'#${1}PARAM_INT#s;
