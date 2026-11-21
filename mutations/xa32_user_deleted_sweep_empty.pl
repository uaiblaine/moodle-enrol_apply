# XA32: the user_deleted observer does nothing, so a comment outlives a user deleted while the plugin is disabled.
s#public static function user_deleted\(user_deleted \$event\): void \{\n\s+self::sweep_orphaned_applicationinfo\(\);#public static function user_deleted(user_deleted \$event): void {#;
