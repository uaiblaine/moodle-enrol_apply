# FG14: another method's groups count as a leak whatever role it grants.
s#static fn\(stdClass \$other\): bool => \\enrol_apply\\local\\decidingrole::method_decides\(\$other\)#static fn(stdClass \$other): bool => true#;
