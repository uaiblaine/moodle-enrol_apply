# CZ: tell every empty queue that its filters matched nothing. A method with no applications at all
# then reads "No application matches the filters you have applied" over a page with no filters
# applied, which sends the reader looking for a control that is not there. The guard is one
# condition and its absence changes nothing a filtered test would notice. Repointed when both
# branches became the Design System's empty state (Q1): the condition now chooses between them.
s{        if \(!\$this->is_narrowed\(\)\) \{\n            echo html_writer::div\(}{        if (false) \{\n            echo html_writer::div(}s;
