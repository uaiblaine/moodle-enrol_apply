# XA32: the user_deleted observer sweeps no comment, so a comment outlives a user deleted while the plugin is disabled.
s#\n\s+self::sweep_orphaned_applicationinfo\(\);(\n\s+submission::pseudonymise_user\(\(int\) \$event->objectid\);)#$1#;
