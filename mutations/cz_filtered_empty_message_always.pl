# CZ: tell every empty queue that its filters matched nothing. A method with no applications at all
# then reads "No application matches the filters you have applied" over a page with no filters
# applied, which sends the reader looking for a control that is not there. The guard is one
# early return and its absence changes nothing a filtered test would notice.
s{        if \(!\$this->is_narrowed\(\)\) \{\n            parent::print_nothing_to_display\(\);\n\n            return;\n        \}\n\n}{}s;
