# RP5: the attention line names an attention signal before an over-limit one (RP-D14).
s#\$worst = \$flagged \? \(\$over \?: \$warn\)\[0\]#\$worst = \$flagged ? (\$warn ?: \$over)[0]#;
