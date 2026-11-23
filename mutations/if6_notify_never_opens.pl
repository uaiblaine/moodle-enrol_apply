# IF6: Who decides and who is notified stays closed even when the recipients are not the default.
s#\|\| \(string\) \(\$instance->customtext3 \?\? ''\) !== \(string\) \$defaults\['customtext3'\],#|| false,#;
