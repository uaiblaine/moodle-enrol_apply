# PF5: claim another field's elements because their names start with this one's.
s{\n            foreach \(\$longer as \$other\) \{\n                if \(\$name === \$other \|\| str_starts_with\(\$name, \$other \. '_'\)\) \{\n                    continue 2;\n                \}\n            \}\n}{\n}s;
