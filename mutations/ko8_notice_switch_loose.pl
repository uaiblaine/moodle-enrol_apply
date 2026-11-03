# KO8: any non-empty stored switch turns the notice on, a restored unknown value included.
s#\(\$instance->customchar2 \?\? ''\) === self::NOTICE_ON;#!empty(\$instance->customchar2);#;
