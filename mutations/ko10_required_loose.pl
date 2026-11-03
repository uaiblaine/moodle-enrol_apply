# KO10: any value that is neither off nor "any" reads as key only, a restored unknown mode included.
s#return \(\$instance->customchar1 \?\? self::MODE_OFF\) === self::MODE_REQUIRED;#return !in_array(\$instance->customchar1 ?? self::MODE_OFF, [self::MODE_OFF, self::MODE_ANY], true);#;
