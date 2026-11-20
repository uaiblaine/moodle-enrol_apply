# QG9: `none` matches only a record with no group id, so an application whose group was deleted is reached by no value.
s#JOIN \{groups\} nonegroup ON nonegroup\.id = \{\$group\}\n(\s*)WHERE nonesub\.userenrolmentid = ue\.id#\n$1WHERE nonesub.userenrolmentid = ue.id AND {\$group} > 0#;
