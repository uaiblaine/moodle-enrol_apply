# SF10: a delete for every user of a user context leaves the stored filters.
s/(if \(\$context->contextlevel == CONTEXT_USER\) \{\n)\s*reportpersistents::delete_user_filters\(\(int\) \$context->instanceid\);\n(\s*return;\n\s*\}\n\s*if \(!\$context instanceof context_course\) \{\n\s*return;\n\s*\}\n\n\s*\$sql = "SELECT ai\.id)/$1$2/;
