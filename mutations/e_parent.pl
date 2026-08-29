# E: drop core's own actions instead of appending to them.
s{\$actions = parent::get_user_enrolment_actions\(\$manager, \$ue\);}{\$actions = [];}s;
