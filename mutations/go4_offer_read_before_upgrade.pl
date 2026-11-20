# GO4: read the offer before the upgrade has run.
s{        if \(!instanceoptions::schema_ready\(\)\) \{\n            return \[\];\n        \}\n}{};
