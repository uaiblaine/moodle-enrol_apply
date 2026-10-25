# KS12: the review page names the key group to a reader who may not see the course's groups.
s/        if \(!\$group \|\| !has_capability\('enrol\/apply:manageapplications', \$coursecontext\)\) \{/        if (!\$group) {/;
