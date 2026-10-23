# MV2: a column in hiddenuserfields shows to everybody holding the identity capability.
s/\$hiddenhere = in_array\(\$column, \$hidden, true\) && !\$canviewhidden;/\$hiddenhere = false;/;
