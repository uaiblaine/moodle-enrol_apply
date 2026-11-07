# ML2: warning from 60 % itself instead of above it.
s#        return \$after \* 100 > \$total \* 60 \? self::METER_WARNING : self::METER_PRIMARY;#        return \$after * 100 >= \$total * 60 ? self::METER_WARNING : self::METER_PRIMARY;#;
