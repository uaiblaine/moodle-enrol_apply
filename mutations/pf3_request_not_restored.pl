# PF3: leave the field's values in the request after the type has read them.
s{\n            foreach \(\$saved as \$name => \$previous\) \{\n.*?\n            \}\n        \}}{\n        }s;
