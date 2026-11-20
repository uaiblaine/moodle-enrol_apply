# GD5: the scope (and the queue's group filter) reads the key group alone.
s#\$group = self::application_group_sql\('scopesub'\);#\$group = 'scopesub.keygroupid';#;
