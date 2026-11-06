# RV8: an application for review is written whatever the groups' seats.
s#(function apply_for_review\(.*?)\$full = \\enrol_apply\\local\\groupseats::full\(\$targets\);#${1}\$full = [];#s;
