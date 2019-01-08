<?php

class MnistDb extends SQLite3
{
    function __construct()
    {
		unlink('mnist.db');
        $this->open('mnist.db');
    }
}

$db = new MnistDb();
$db->exec('
CREATE TABLE `images` (
	`id`			INTEGER PRIMARY KEY AUTOINCREMENT,	
	`label`			INTEGER NULL CHECK(label >= 0 and label <= 9),
	`prediction`	INTEGER NULL CHECK(prediction >= 0 and prediction <= 9),
	`pixels`		TEXT NOT NULL
)
');

$file = fopen("train.csv","r");
$row = 0;
$db->exec('BEGIN;');
while (($data = fgetcsv($file)) !== FALSE) {
	if ($row > 0) {
		$label_val = $data[0];
		$stmt = $db->prepare('INSERT INTO images (label,pixels) VALUES (:label,:pixels)');		
		$stmt->bindValue(':label',			$data[0],								SQLITE3_INTEGER);
		$stmt->bindValue(':pixels',			implode(",", array_slice($data, 1)),	SQLITE3_TEXT);
		$stmt->execute();
		echo "row: " . $row . "\n";
	}
	$row++;
}
$db->exec('COMMIT;');
fclose($file);

$file = fopen("test.csv","r");
$row = 0;
$db->exec('BEGIN;');
while (($data = fgetcsv($file)) !== FALSE) {
	if ($row > 0) {		
		$stmt = $db->prepare('INSERT INTO images (label,pixels) VALUES (:label,:pixels)');		
		$stmt->bindValue(':label',			NULL,									SQLITE3_INTEGER);
		$stmt->bindValue(':pixels',			implode(",", array_slice($data, 0)),	SQLITE3_TEXT);
		$stmt->execute();
		echo "row: " . $row . "\n";
	}
	$row++;
}
$db->exec('COMMIT;');
fclose($file);

$db->close();

?>