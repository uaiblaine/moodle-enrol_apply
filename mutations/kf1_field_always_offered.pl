# KF1: the key field is offered whatever the instance's option says.
s#if \(!groupkey::enabled\(\$instance\)\) \{\n\s*return;\n\s*\}\n##;
