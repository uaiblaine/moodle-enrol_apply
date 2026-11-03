# KO7: a key-only instance announces every admission whatever its switch says.
s#return !self::required\(\$instance\) \|\| \(\$instance->customchar2 \?\? ''\) === self::NOTICE_ON;#return true;#;
