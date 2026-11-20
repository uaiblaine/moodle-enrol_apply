# RS5: restore the chosen group whatever the stored version.
s{        \$fields = \$this->key_fields\(\$data\);\n        if \(!enrol_get_plugin\('apply'\)->options_schema_ready\(\)\) \{\n            return \$fields;\n        \}\n}{        \$fields = \$this->key_fields(\$data);\n};
