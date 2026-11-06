# KS15: a group at exactly its seats is not called full, on the queue's chooser or the review page's
# cards (repointed for slice V3 to group_facts()).
s|                'full' => \$hasseats && \$members >= \$total,|                'full' => \$hasseats && \$members > \$total,|;
