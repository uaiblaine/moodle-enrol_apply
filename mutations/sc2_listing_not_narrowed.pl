# SC2: the queue's listing ignores the group narrowing its scope carries.
s#if \(\$this->scope->groups !== null\) \{\n(\s*)\[\$groupwhere, \$groupparams\] = groupscope::where#if (false) {\n${1}[\$groupwhere, \$groupparams] = groupscope::where#;
