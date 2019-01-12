<?php 
	class MnistDb extends SQLite3
	{
		function __construct()
		{		
			$this->open('mnist.db');
		}
    }
    
	$db						= new MnistDb();
    $image_data             = NULL;
    $stream = $db->openBlob('images', 'pixels', 1);
    $image_data = stream_get_contents($stream);
    fclose($stream);
    header("Content-type: image/png");
    echo $image_data;
?>