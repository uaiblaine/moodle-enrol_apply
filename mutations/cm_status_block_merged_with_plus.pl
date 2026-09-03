# CM: merge the status keys with + instead of array_merge. The context already carries
# 'hasstatus' => false for the scopes that span methods, and + keeps the LEFT side on a duplicate
# key - so the whole status block silently stops rendering on every instance-scoped queue while
# every other tile goes on working. This shipped, and was found by looking at the page.
s{        return array_merge\(\$context, \[\n            'hasstatus' => true,}{        return \$context + [\n            'hasstatus' => true,}s;
s{            'remainingtext' => get_string\('queueremaining', 'enrol_apply', \$remaining\),\n        \]\);}{            'remainingtext' => get_string('queueremaining', 'enrol_apply', \$remaining),\n        ];}s;
