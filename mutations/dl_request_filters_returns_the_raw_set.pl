# DL: return what optional_param() left rather than what the table will apply. manage.php builds
# the page url and the decision form's action from this, while the table's own base url comes from
# url_params() - so the two definitions that each call themselves THE definition disagree.
s{\$value = queuefilter::clean\(\$offered, optional_param\(\$token, '', PARAM_NOTAGS\)\);\n            if \(\$value !== null\) \{}{\$value = trim(optional_param(\$token, '', PARAM_NOTAGS));\n            if (\$value !== '') \{}s;
s{if \(\$value !== '' && queuefilter::day_bounds\(\$value, null\)\[0\] !== null\) \{}{if (\$value !== '') \{}s;
