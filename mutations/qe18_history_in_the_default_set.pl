# QE18: the earlier applications are in the default set (CQ-D8: an optional column).
s#\$catalogue\['history'\] = self::entry\(get_string\('queuehistory', 'enrol_apply'\), false, false\);#\$catalogue['history'] = self::entry(get_string('queuehistory', 'enrol_apply'), false, true);#;
