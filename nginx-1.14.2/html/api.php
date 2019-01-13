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
	//$test_images			= array();
	$predict_images			= array();

	$stmt = $db->prepare('SELECT id,label FROM images ORDER BY RANDOM() ');	
	$result =  $stmt->execute();
	$count = 0;
	while($row=$result->fetchArray($mode = SQLITE3_NUM))
	{	   	   
	   $obj				= new stdClass;
	   $obj->id			= $row[0];
	   $obj->label		= $row[1];

	   $stream 			= $db->openBlob('images', 'pixels', $obj->id);
	   $obj->pixels		= base64_encode(stream_get_contents($stream));
	   fclose($stream);

	   if (NULL == $obj->label) 
	   {
			array_push($predict_images, $obj);	
	   }
	   else {
		   //42000 rows in training.csv
		   //28000 rows in test.csv -- the ones needed to predict
		   //
		   //42000 * 0.2 holdout = 8400
		   //
		   //8400 test images
		   //33600 train images
		   //---------------------
		   //70000  total rows
		//    if ($count > 8400) 
		//    {				
		// 		array_push($train_images, $obj);
		//    }
		//    else 
		//    {
		// 		array_push($test_images, $obj);	   
		//    }	   

		   array_push($train_images, $obj);	   
		   $count++;
	   }	   
	}
	$ret							= new stdClass;
	$ret->train						= $train_images ;
	//$ret->test						= $test_images ;
	$ret->predict					= $predict_images ;

	header('Content-type: application/json');
	echo json_encode($ret);
?>