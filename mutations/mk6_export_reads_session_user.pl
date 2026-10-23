# MK6: the export reads the session user, as core_group export_groups() does.
s/\$params\[\x27userid\x27\] = \$userid;\n(\s*)\$params\[\x27component\x27\]/\$params[\x27userid\x27] = (int) \$GLOBALS[\x27USER\x27]->id;\n$1\$params[\x27component\x27]/;
