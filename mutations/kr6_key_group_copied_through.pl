# KR6: the restore copies the archived key group through instead of mapping it.
s#\(int\) \$this->get_mappingid\('group', \$data->keygroupid \?\? 0\)#(int) (\$data->keygroupid ?? 0)#;
