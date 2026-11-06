# QG9: `none` matches only a record with no key group id, so an application whose group was deleted is reached by no value.
s#JOIN \{groups\} nonegroup ON nonegroup\.id = nonesub\.keygroupid\n(\s*)WHERE nonesub\.userenrolmentid = ue\.id#\n$1WHERE nonesub.userenrolmentid = ue.id AND nonesub.keygroupid > 0#;
