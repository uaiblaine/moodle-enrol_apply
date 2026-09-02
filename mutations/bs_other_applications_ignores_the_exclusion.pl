# BS: report every application awaiting a decision, including the ones this operation just took.
# A DEFERRAL leaves its row awaiting a decision, so the operator is told to go and decide the
# application they have this second deferred.
s#        if \(\$excludeueids\) \{\n            \[\$notinsql, \$notinparams\] = \$DB->get_in_or_equal\(\n                \$excludeueids,\n                SQL_PARAMS_NAMED,\n                'decided',\n                false\n            \);\n            \$wheres\[\] = "ue.id \{\$notinsql\}";\n            \$params \+= \$notinparams;\n        \}\n\n##s;
