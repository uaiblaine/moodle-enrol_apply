# XA48: the form's questions are read through can_edit(), which refuses everybody.
s#application_handler::create\(\(int\) \$instance->id\)->get_fields\(\);#application_handler::create((int) \$instance->id)->get_editable_fields(0);#;
