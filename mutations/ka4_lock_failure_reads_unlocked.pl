# KA4: a throttle lock not obtained reads as unlocked.
s#(public static function locked\(int \$courseid, int \$userid\): bool \{\n        if \(!self::hold\(\$courseid, \$userid\)\) \{\n            return) true;#$1 false;#;
