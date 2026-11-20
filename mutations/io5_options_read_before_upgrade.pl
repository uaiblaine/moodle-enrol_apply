# IO5: read the options row before the upgrade has run.
s{        if \(!self::schema_ready\(\)\) \{\n            return self::defaults\(\);\n        \}\n}{};
