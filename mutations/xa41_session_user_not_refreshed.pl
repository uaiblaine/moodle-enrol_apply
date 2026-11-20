# XA41: the session's $USER is not refreshed after a write to it.
s#        self::refresh_session_user\(\(int\) \$user->id, array_keys\(\(array\) \$usernew\), \(bool\) \$custom\);\n##s;
