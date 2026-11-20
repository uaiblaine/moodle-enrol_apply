# IF6: Notifications stays closed even when the recipients are not the default.
s#'sectionnotify' => \(string\) \(\$instance->customtext3 \?\? ''\) !== \(string\) \$defaults\['customtext3'\],#'sectionnotify' => false,#;
