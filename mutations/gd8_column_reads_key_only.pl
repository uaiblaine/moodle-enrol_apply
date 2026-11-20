# GD8: the queue's group column joins the key group alone.
s#\$appgroup = groupscope::application_group_sql\('kgs'\);#\$appgroup = 'kgs.keygroupid';#;
