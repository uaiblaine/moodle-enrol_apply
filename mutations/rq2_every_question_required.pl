# RQ2: every application question reads as required, whatever its own configdata says.
s#            if \(\$field->get_configdata_property\('required'\)\) \{#            if (true) {#;
