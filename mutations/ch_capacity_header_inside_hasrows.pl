# CH: make the decision context depend on the queue having rows. A method whose applicant limit is
# reached holds an EMPTY queue - every application counted against the limit may be deferred, and a
# deferred one is freed by nothing - so the numbers would vanish in exactly the state whose only
# symptom is "there is nothing here".
#
# REPOINTED 2026-09-04, and the file moved with it. The guard used to be the header's position
# OUTSIDE `{{#hasrows}}` in manage.mustache; PR 3b deleted that section altogether, so the pattern
# named markup that no longer exists. What holds the behaviour now is the renderer building
# capacityhtml unconditionally, which is what this breaks.
s{            'capacityhtml' => \$this->render_from_template\(.*?\n            \),\n}{            'capacityhtml' => \$table->totalrows ? \$this->render_from_template(\n                'enrol_apply/queue_capacity',\n                \$this->queue_capacity_context(\$instance, \$table->scope_total())\n            ) : '',\n}s;
