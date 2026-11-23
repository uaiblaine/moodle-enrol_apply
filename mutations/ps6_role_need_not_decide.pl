# PS6: "Moderator recruitment" takes an offered role whether it decides or not.
s#static fn\(int \$roleid\): bool => in_array\(\$roleid, \$deciding, true\)#static fn(int \$roleid): bool => true#;
