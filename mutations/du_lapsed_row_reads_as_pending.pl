# DU: stop asking the queue whether a decision is awaited, so every row that is neither active nor
# deferred reads as pending. An applicant whose approval lapsed under a suspending expiredaction is
# then told "submitted and waiting for a decision" while the queue holds nothing for anyone to decide.
s|default => queue::is_awaiting_decision\(\$userenrolment\) \? self::PENDING : self::INACTIVE,|default => self::PENDING,|;
