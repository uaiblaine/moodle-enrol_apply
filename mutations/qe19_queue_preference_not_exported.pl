# QE19: the queue's grid is declared but not exported on a subject access request.
s#\n\s*\$value = get_user_preferences\(queuepreference::NAME, null, \$userid\);\n\s*if \(\$value !== null\) \{#\n        \$value = null;\n        if (\$value !== null) {#;
