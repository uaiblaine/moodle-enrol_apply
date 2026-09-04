# DS: match the category exactly and drop its subtree. Filtering by a parent category then finds
# only the courses sitting directly in it, and a site that files its courses one level down gets an
# empty queue from a filter that names the right category.
s{\$wheres\[\] = "c\.category IN \(\n                             SELECT cc\.id\n                               FROM \{course_categories\} cc\n                              WHERE cc\.id = :queuecategoryid\n                                 OR " \. \$DB->sql_like\('cc\.path', ':queuecategorypath', false\) \. "\n                         \)";}{\$wheres[] = "c.category = :queuecategoryid";}s;
