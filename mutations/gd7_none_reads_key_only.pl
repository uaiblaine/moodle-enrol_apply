# GD7: the queue's "Without a group" reads the key group alone, so a chosen application lands there.
s#\$group = groupscope::application_group_sql\('nonesub'\);#\$group = 'nonesub.keygroupid';#;
