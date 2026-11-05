# SC50: the report reads the key group alone, not the decided groups.
s#return \["\(\{\$alias\}\.keygroupid \{\$insql\} OR " \. implode\(' OR ', \$likes\) \. '\)', \$params\];#return ["({\$alias}.keygroupid {\$insql})", \$params];#;
