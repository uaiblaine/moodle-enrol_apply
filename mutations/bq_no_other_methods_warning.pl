# BQ: stop reporting the applications the dispatch never reached. One person holding an
# application on each of two methods is the case core says NOTHING about - nothing is "removed",
# so the operator reads a clean success over a half-done decision.
s#        \$othernotice = \$this->other_applications_notice\(\$manager, \$users, 'report'\);\n        if \(\$othernotice !== ''\) \{\n            \\core\\notification::warning\(\$othernotice\);\n        \}\n\n##s;
