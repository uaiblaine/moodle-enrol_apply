# KW8: the admission joins the key's group only, not the instance's own list.
s#\$ids = array_values\(array_unique\(array_merge\(\[\$keygroupid\], array_map\('intval', \$listed\)\)\)\);#\$ids = [\$keygroupid];#;
