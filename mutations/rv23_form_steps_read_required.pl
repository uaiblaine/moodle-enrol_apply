# RV23: the form's two steps read required(), so the reviewed mode gets a one-step form.
s#if \(groupkey::mandatory\(\$instance\)\) \{\n(\s*)\$this->keystep = 1;#if (groupkey::required(\$instance)) {\n${1}\$this->keystep = 1;#;
