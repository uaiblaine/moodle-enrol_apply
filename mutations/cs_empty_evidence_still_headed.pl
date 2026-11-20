# CS: keep the card's heading when there is nothing under it. Above the breakpoint this is
# invisible - the heading is hidden and the cell is empty either way - and below it every
# application that stored no answers grows a section headed "Submitted with the application" with
# nothing beneath it, which reads as data that failed to load rather than data that was never
# given. Repointed when the pills became one line of answers (Q1).
s{        if \(\$answers === \[\]\) \{\n            return '';\n        \}\n\n}{}s;
