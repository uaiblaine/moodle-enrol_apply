# F4F: drop the primary-key cursor from the retention sweep. A row that fails to purge is then
# selected again on every iteration until the time budget runs out. fix_sql_params() tolerates
# the surplus :lastid parameter, so nothing else notices.
s# AND s\.id > :lastid##s;
