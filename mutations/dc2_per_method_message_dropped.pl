# DC2: keep the grouping and give every group the plain message, as if the sheet's per-method
# boxes had never been posted.
s#'message' => \$key \? \$permethod\[\$key\] : \$message\]#'message' => \$message]#;
