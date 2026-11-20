# GC16: a pending application's wording never names the chosen group.
s#return \$state === self::PENDING \? self::chosen\(\$userenrolment\) : \[\$state, null\];#return [\$state, null];#;
