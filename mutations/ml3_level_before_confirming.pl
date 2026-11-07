# ML3: the level read from the occupancy before confirming instead of after it.
s#        \$level = self::meter_level\(\$after, \$total\);#        \$level = self::meter_level(\$others, \$total);#;
