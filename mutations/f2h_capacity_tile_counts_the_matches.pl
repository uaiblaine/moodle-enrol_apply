# F2H: count the filtered rows in the capacity header's first tile instead of the whole scope.
s#\$this->queue_capacity_context\(\$instance, \$table->scope_total\(\)\)#\$this->queue_capacity_context(\$instance, (int) \$table->totalrows)#s;
