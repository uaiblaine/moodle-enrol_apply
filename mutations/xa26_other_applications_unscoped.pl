# XA26: other_applications() without the operator's group narrowing.
s#if \(\$groups !== null\) \{\n            \[\$groupwhere, \$groupparams\] = groupscope::where\(\$groups\);#if (false) {\n            [\$groupwhere, \$groupparams] = groupscope::where(\$groups ?? []);#;
