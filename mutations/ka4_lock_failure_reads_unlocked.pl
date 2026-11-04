# KA4: a throttle lock not obtained reads as unlocked.
s#(public static function locked\(int \$instanceid, int \$userid\): bool \{\n        if \(!self::hold\(\$instanceid, \$userid\)\) \{\n            return) true;#$1 false;#;
