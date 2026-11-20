# F2H: count the filtered rows in the status control's "All" (once the capacity header's first
# tile) instead of the whole scope.
#
# Repointed in V2, when the call grew the deferred count and the narrowing as further arguments,
# and again in Q2, when the two plain counts moved from the band into the status control (MQ-D3).
s#\$total = \$table->scope_total\(\);#\$total = (int) \$table->totalrows;#;
