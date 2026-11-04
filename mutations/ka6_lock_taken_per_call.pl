# KA6: the throttle lock is asked for on every call instead of once per request.
s#if \(!array_key_exists\(\$resource, self::\$held\)\) \{#if (true) \{#;
