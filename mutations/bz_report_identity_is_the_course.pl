# BZ: drop the itemid, so every apply method of a course shares one report persistent - and
# therefore one stored scope, because reportbuilder_user_filter is keyed on (reportid, user).
# The scoping then holds only for the initial page render: sorting, paging and the download all
# carry nothing but the report id, so the last method opened answers every later request.
s#return system_report_factory::create\(self::class, \$context, 'enrol_apply', 'method', \$enrolid\);#return system_report_factory::create(self::class, \$context);#;
