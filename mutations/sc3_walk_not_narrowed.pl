# SC3: the previous/next walk ignores the group narrowing, so it steps onto other groups' applications.
s#if \(\$scope->groups !== null\) \{\n(\s*)\[\$groupwhere, \$groupparams\] = groupscope::where\(\$scope->groups\);#if (false) {\n${1}[\$groupwhere, \$groupparams] = groupscope::where(\$scope->groups);#;
