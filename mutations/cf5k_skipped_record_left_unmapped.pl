# CF5K: a skipped record registers no mapping, so its answers follow the record restored before it.
s{\$this->set_mapping\('enrol_apply_submission', \$data->id, \$this->restore_submission\(\$data\)\);}{if (\$newid = \$this->restore_submission(\$data)) {\n            \$this->set_mapping('enrol_apply_submission', \$data->id, \$newid);\n        }}s;
