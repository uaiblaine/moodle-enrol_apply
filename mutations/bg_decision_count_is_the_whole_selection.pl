# BG: report the whole selection as decided rather than the rows the method reached. The page
# then prints "Applications updated" for a post that changed nothing - which is what it did
# before the count existed, because every decision method skips a row it will not act on and
# skips it in silence.
s|        return \$decided;\n    \}\n\n    /\*\*\n     \* Cancel the given applications, unenrolling the applicants\.|        return count(\$enrols);\n    }\n\n    /**\n     * Cancel the given applications, unenrolling the applicants.|s;
