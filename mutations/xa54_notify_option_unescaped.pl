# XA54: a decider's name reaches the notification select unescaped.
s#\$choices\[\$userid\] = s\(fullname\(\$user\)\);#\$choices[\$userid] = fullname(\$user);#;
