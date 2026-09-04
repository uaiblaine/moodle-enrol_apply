# DP: format the date chip server-side. The same chip is redrawn client-side from the input's own
# value on every refresh, and no server formatting is reproducible there - so the chip changes
# spelling the moment anything else on the bar is touched.
s{                    get_string\(\$stringid, 'enrol_apply'\),\n                    \$value,}{                    get_string(\$stringid, 'enrol_apply'),\n                    userdate(\\enrol_apply\\local\\queuefilter::day_bounds(\$value, null)[0], get_string('strftimedateshort', 'langconfig')),}s;
