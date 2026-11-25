# SF11: a delete for the user leaves their stored filters.
s/\n\s*if \(self::own_user_context\(\$contextlist\)\) \{\n\s*reportpersistents::delete_user_filters\(\(int\) \$contextlist->get_user\(\)->id\);\n\s*\}//;
