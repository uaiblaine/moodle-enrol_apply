# DU: stop asking the queue whether a decision is awaited, so every row that is not active reads as
# pending or deferred. An applicant whose approval lapsed under a suspending expiredaction is then
# told "submitted and waiting for a decision" while the queue holds nothing for anyone to decide.
s|        if \(!queue::is_awaiting_decision\(\$userenrolment\)\) \{\n            return self::INACTIVE;\n        \}\n\n||;
