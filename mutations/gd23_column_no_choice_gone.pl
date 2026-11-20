# GD23: a record with no choice reads as "a group deleted since".
s#        if \(\(int\) \(\$row->chosengroupid \?\? 0\) <= 0\) \{\n            return '';\n        \}\n##;
