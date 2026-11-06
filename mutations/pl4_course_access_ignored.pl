# PL4: a reader who cannot enter the course is linked, though require_login($course) refuses them.
s#        if \(!self::enters\(\$course\)\) \{#        if (false) {#;
