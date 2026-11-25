# MV3: nobody may see a hidden column, override or not.
s#\$canviewhidden = !\$hidden \|\| \\enrol_apply\\local\\groupscope::holds_in_course\(\$hiddencapability, \$context, \$userid\);#\$canviewhidden = !\$hidden;#;
