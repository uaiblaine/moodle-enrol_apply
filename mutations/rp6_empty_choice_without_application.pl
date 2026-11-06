# RP6: the empty-choice fallback is read without the application, so a key's group is left out.
s#enrol_get_plugin\('apply'\)->default_approval_groups\(\$instance, \(int\) \$application->id\)#enrol_get_plugin('apply')->default_approval_groups(\$instance, 0)#;
