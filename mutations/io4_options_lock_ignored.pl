# IO4: go ahead without the lock that serialises a method's first write.
s{        if \(!\$lock\) \{\n            throw new moodle_exception\('locktimeout'\);\n        \}\n        try \{}{        try \{};
s{        \} finally \{\n            \$lock->release\(\);\n        \}}{        \} finally \{\n            if (\$lock) \{\n                \$lock->release();\n            \}\n        \}};
