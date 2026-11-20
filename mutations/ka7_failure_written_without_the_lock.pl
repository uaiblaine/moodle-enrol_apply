# KA7: a wrong key is written by a request that does not hold the throttle lock.
s#(public static function record_failure\(int \$courseid, int \$userid\): void \{\n(?:\s*/\*.*?\*/\n)?        if \()!self::hold\(\$courseid, \$userid\)#$1false#s;
