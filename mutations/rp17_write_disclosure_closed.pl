# RP17: the message disclosure stays folded over what was typed (RP-D7).
s#'writeopen' => trim\(\$message\) !== '' \|\| trim\(\$note\) !== '',#'writeopen' => false,#;
