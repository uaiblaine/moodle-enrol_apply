# CV: search a column because it is in the SELECT rather than because this reader may see it.
# u.email is projected on every scope - core's for_userpic() pulls it in - including the mentee
# scope, where identity::fields() returns nothing at all. A mentor can then ask "does this
# applicant's address contain <guess>?" and read the answer off the result count, one guess at a
# time, for a field the page never prints to them.
s{        foreach \(\$this->identitymappings as \$expression\) \{\n            \$columns\[\] = \$expression;\n        \}}{        \$columns[] = 'u.email';}s;
