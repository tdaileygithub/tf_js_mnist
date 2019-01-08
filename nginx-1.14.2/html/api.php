<?php 

	class MnistDb extends SQLite3
	{
		function __construct()
		{		
			$this->open('mnist.db');
		}
	}
	
	$db				= new MnistDb();
	$train_images	= array();
	$test_images	= array();
	$predict_images = array();

	$stmt = $db->prepare('SELECT id,label,pixels FROM images');	
	$result =  $stmt->execute();
	$count = 0;
	while($row=$result->fetchArray())
	{	   	   
	   $obj			= new stdClass;
	   $obj->id		= $row[0];
	   $obj->label	= $row[1];
	   $obj->pixels = $row[2];

	   if (NULL == $obj->label) 
	   {
			array_push($predict_images, $obj);	
	   }
	   else {
		   //42000 rows in the training.csv
		   //70000  total rows
		   if ($count > 8400) 
		   {
				array_push($train_images, $obj);	   
		   }
		   else 
		   {
				array_push($test_images, $obj);	   
		   }	   
		   $count++;
	   }	   
	}
	$ret			= new stdClass;
	$ret->train		= $train_images ;
	$ret->test		= $test_images ;
	$ret->predict	= $predict_images ;

	header('Content-type: application/json');
	echo json_encode($ret);
?>