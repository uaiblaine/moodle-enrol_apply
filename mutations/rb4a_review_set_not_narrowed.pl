# RB4A: the report's reviewable set reads the deciding capability in the course without the
# separate-groups narrowing, so a moderator would get Review on another group's applications.
s#if \(\$groups !== null\) \{(\s*)\[\$groupwhere, \$groupparams\] = groupscope::where\(\$groups\);(\s*)\$wheres\[\] = \$groupwhere;#if (false) {${1}[\$groupwhere, \$groupparams] = groupscope::where(\$groups);${2}\$wheres[] = \$groupwhere;#s;
