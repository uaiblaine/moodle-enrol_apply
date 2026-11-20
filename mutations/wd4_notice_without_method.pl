# WD4: the notice does not hand allows_group() its method.
s#(groupscope::DECIDE,\n\s*)\(int\) \$instance->id(\n\s*\)\n\s*\) \{)#${1}0$2#;
