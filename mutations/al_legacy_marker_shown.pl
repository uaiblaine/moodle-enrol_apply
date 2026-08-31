# AL: drop the legacy-recipient-list guard, so a value a restore carried in from a pre-2016
# archive is shown to the applicant as the question they are answering.
s{trim\(\$custom\) === '' \|\| self::is_legacy_recipient_list\(\$custom\)}{trim(\$custom) === ''}s;
