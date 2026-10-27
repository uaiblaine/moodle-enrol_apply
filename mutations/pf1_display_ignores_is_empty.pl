# PF1: let a value the type calls empty be shown, so an unset date reads "Not set" in the snapshot.
s{\n        if \(\$holder->is_empty\(\)\) \{\n            return '';\n        \}\n}{\n}s;
