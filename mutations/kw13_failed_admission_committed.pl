# KW13: an admission that failed part way is committed instead of rolled back.
s#\$transaction->rollback\(\$e\);#\$transaction->allow_commit();\n            throw \$e;#;
