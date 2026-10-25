# KS2: the admission is read from the outcome alone, so an approval a person made later over the
# same record is still described as the key's.
s|\n            && \$key->decidedby === \$key->userid;|;|;
