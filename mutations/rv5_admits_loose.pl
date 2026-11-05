# RV5: any value but off and review admits, so a restored unknown mode skips the review.
s#return in_array\(\$instance->customchar1 \?\? self::MODE_OFF, \[self::MODE_ANY, self::MODE_REQUIRED\], true\);#return !in_array(\$instance->customchar1 ?? self::MODE_OFF, [self::MODE_OFF, self::MODE_REVIEW], true);#;
