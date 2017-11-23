<?php

$m = new waModel();
$sql = <<<SQL
UPDATE shop_feature child
  JOIN shop_feature parent
    ON
      parent.id = child.parent_id
      AND
      parent.status != child.status
SET child.status = parent.status
WHERE child.parent_id
SQL;

$m->query($sql);
