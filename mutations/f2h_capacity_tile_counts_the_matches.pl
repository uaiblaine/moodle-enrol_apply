# F2H: count the filtered rows in the capacity header's first tile instead of the whole scope.
#
# Repointed in V2, when the call grew the deferred count and the narrowing as further arguments.
s#(\$this->queue_capacity_context\(\n\s*\$instance,\n\s*)\$table->scope_total\(\),#${1}(int) \$table->totalrows,#s;
