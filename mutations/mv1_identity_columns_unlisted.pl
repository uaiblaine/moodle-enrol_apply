# MV1: an identity column shows whether or not showuseridentity names it.
s/\$unlisted = in_array\(\$column, self::IDENTITY_COLUMNS, true\) && !in_array\(\$column, \$identity, true\);/\$unlisted = false;/;
