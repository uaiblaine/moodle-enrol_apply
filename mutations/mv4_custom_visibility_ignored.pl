# MV4: every custom profile field shows, private and hidden ones included.
s/(protected static function custom_visible\(int \$visibility, bool \$viewalldetails, bool \$viewsystem\): bool \{\n)/$1        return true;\n/;
