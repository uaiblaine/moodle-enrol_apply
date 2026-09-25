# F2G: let every category lend its name to a path, so a hidden ancestor is named inside the path
# of a visible category beneath it, to a reader who may not see the ancestor.
s#            if \(!array_key_exists\(\$id, \$visible\)\) \{\n                continue;\n            \}\n##s;
