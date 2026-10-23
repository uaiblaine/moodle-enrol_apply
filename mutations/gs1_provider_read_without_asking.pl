# GS1: the provider is read without asking whether it is installed.
s{if \(!static::available\(\)\) \{\n\s*return array_fill_keys[^\n]*\n\s*\}\n}{}s;
