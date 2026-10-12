# CF4P: the restore does not remap field ids through the restored fields.
s{\$newid = \(int\) \$this->get_mappingid\('enrol_apply_appfield', \$oldid\);}{\$newid = 0;}s;
