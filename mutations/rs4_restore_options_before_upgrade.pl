# RS4: the options handler writes whatever the stored version.
s{        if \(!\$enrolid \|\| !enrol_get_plugin\('apply'\)->options_schema_ready\(\)\) \{\n            return;\n        \}\n\n        instanceoptions::set}{        if (!\$enrolid) \{\n            return;\n        \}\n\n        instanceoptions::set};
