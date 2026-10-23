# MV6: the identity capability is judged for the session user, not the reader named.
s/if \(!has_capability\(\x27moodle\/site:viewuseridentity\x27, \$context, \$userid\)\) \{/if (!has_capability(\x27moodle\/site:viewuseridentity\x27, \$context)) {/;
