# MV7: every recipient of the new-application e-mail gets every submitted field.
s/static fn\(array \$pair\): bool => \$formatter::shows\(\(string\) \$pair\[\x27key\x27\], \$visible\)/static fn(array \$pair): bool => true/;
