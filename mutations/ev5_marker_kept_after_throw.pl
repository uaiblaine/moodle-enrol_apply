# EV5: defer() undoes its count only when the code it ran returned, so a write that threw leaves
# the approval marked as owed and the next approval of it in the same request logs nothing.
s#try \{\n            \$run\(\);\n        \} finally \{#{\n            \$run();\n        }\n        {#s;
