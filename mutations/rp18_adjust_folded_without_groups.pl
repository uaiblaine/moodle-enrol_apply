# RP18: "Adjust" stays folded when the method gives no group (RP-D5).
s#'adjustopen' => \$anyfull \|\| \(\$hasgroups && !\$ticked && !\$defaults\),#'adjustopen' => \$anyfull,#;
