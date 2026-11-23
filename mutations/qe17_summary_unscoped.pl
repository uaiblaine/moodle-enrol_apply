# QE17: the summary's queue figures count the method's whole queue, not the reader's scope.
s#\[\$wheres, \$params\] = \$this->scope_where\(\);\n(\s*)\$params\['figuresdeferred'\]#[\$wheres, \$params] = \\enrol_apply\\local\\queue::awaiting_decision_where();\n$1\$params['figuresdeferred']#;
