<?php 

	class MnistDb extends SQLite3
	{
		function __construct()
		{		
			$this->open('mnist.db');
		}
	}
	
	$db						= new MnistDb();
	$train_images			= array();
	$predict_images			= array();

	$stmt = $db->prepare('SELECT id,label FROM images ORDER BY RANDOM() ');	
	$result =  $stmt->execute();	
	while($row=$result->fetchArray($mode = SQLITE3_NUM))
	{	   	   
	   $obj				= new stdClass;
	   $obj->id			= $row[0];
	   $obj->label		= $row[1];	   
	
	   $stream 			= $db->openBlob('images', 'pixels', $obj->id);
	   $obj->pixels		= base64_encode(stream_get_contents($stream));
	   fclose($stream);

	   if (is_null($obj->label))
	   {
		   $predict_images[] = $obj;
	   }
	   else 
	   {
			$train_images[] = $obj;
	   }	   
	}
	$ret							= new stdClass;
	$ret->train						= $train_images ;
	$ret->predict					= $predict_images ;

	header('Content-type: application/json');
	echo json_encode($ret);
?>