# RV3: any value but off and any reads as mandatory, so a restored unknown mode refuses everybody.
s#return in_array\(\$instance->customchar1 \?\? self::MODE_OFF, \[self::MODE_REQUIRED, self::MODE_REVIEW\], true\);#return !in_array(\$instance->customchar1 ?? self::MODE_OFF, [self::MODE_OFF, self::MODE_ANY], true);#;
