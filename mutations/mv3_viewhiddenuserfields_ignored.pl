# MV3: nobody may see a hidden column, override or not.
s/\$canviewhidden = !\$hidden \|\| has_capability\(\$hiddencapability, \$context, \$userid\);/\$canviewhidden = !\$hidden;/;
