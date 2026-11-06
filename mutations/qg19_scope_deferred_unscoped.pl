# QG19: scope_deferred() counts every deferred application of the site, not the listing's scope.
s#\[\$wheres, \$params\] = \$this->scope_where\(\);\n(\s*)\$wheres\[\] = 'ue\.status = :deferredstatus';#[\$wheres, \$params] = \\enrol_apply\\local\\queue::awaiting_decision_where();\n$1\$wheres[] = 'ue.status = :deferredstatus';#;
