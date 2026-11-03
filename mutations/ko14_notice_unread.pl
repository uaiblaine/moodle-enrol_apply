# KO14: apply() sends the managers' notice of an admission without asking the instance.
s#if \(!\$admitted \|\| \\enrol_apply\\local\\groupkey::announces_admissions\(\$instance\)\) \{#if (true) \{#;
