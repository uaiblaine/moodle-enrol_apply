# CH: move the decision context inside the section that depends on the queue having rows. A
# method whose applicant limit is reached holds an EMPTY queue - every application counted against
# the limit may be deferred, and a deferred one is freed by nothing - so the numbers would vanish
# in exactly the state whose only symptom is "there is nothing here".
s{    \{\{\{capacityhtml\}\}\}\n    <div class="enrol_apply-table">\n        \{\{\{tablehtml\}\}\}\n    </div>\n    \{\{#hasrows\}\}}{    <div class="enrol_apply-table">\n        {{{tablehtml}}}\n    </div>\n    {{#hasrows}}\n        {{{capacityhtml}}}}s;
