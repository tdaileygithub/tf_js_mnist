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
	`pixels`		BLOB NULL
)
');

function write_png_from_pixels($pixels_arr) {
	$im 	= imagecreatetruecolor(28, 28);
	$white 	= imagecolorallocate($im, 255, 255, 255);
	$black 	= imagecolorallocate($im, 0, 0, 0);	  

	$index = 0;
	for ($row = 0; $row < 28; $row++) {
		for ($col = 0; $col < 28; $col++) {
			$pix = $pixels_arr[$index];
			if (0 == $pix) {
				imagesetpixel($im, $col, $row, $black);
			} else {
				imagesetpixel($im, $col, $row, $white);
			}
			$index++;
		}
	}		   
	 $path = 'temp.png';
	 imagepng ($im, $path,0,NULL);	 
	 $bytes = file_get_contents($path);
	 unlink($path);
	 return $bytes;
	 
}

$file = fopen("train.csv","r");
$row_ct = 0;
$db->exec('BEGIN;');
while (($data = fgetcsv($file)) !== FALSE) {
	if ($row_ct > 0) {
		$label_val = $data[0];
		 
		$stmt = $db->prepare('INSERT INTO images (label,pixels) VALUES (:label,:pixels)');		
		$stmt->bindValue(':label',			$data[0],								SQLITE3_INTEGER);		
		$stmt->bindValue(':pixels', 		write_png_from_pixels(array_slice($data, 1)), 				\PDO::PARAM_LOB);
		$stmt->execute();

		echo "row: " . $row_ct . "\n";
	}
	$row_ct++;
}
$db->exec('COMMIT;');
fclose($file);

$file = fopen("test.csv","r");
$row_ct = 0;
$db->exec('BEGIN;');
while (($data = fgetcsv($file)) !== FALSE) {
	if ($row_ct > 0) {
		$stmt = $db->prepare('INSERT INTO images (label,pixels) VALUES (:label,:pixels)');		
		$stmt->bindValue(':label',			NULL,									SQLITE3_INTEGER);		
		$stmt->bindValue(':pixels', 		write_png_from_pixels(array_slice($data, 0)), 				\PDO::PARAM_LOB);
		$stmt->execute();
		echo "row: " . $row_ct . "\n";		
	}
	$row_ct++;
}
$db->exec('COMMIT;');
fclose($file);

$db->close();

?>