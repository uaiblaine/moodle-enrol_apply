# F1B: keep only the status half of the queue's predicate in the decision lookup, dropping the
# timeend clause. An approval whose period ended, which process_expirations() re-suspends under
# an expiredaction of suspend, is then decided again from a posted id: cancelled, deferred back
# into the queue, or approved a second time. The surplus :now parameter is tolerated by
# fix_sql_params(), so the query still runs.
s{:enrol AND " \. implode\(' AND ', \$wheres\),}{:enrol AND " . \$wheres[0],};
