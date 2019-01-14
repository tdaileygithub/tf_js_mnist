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

	$stmt = $db->prepare('SELECT id,csv_row FROM images WHERE label IS NULL');	
	$result =  $stmt->execute();	
	while($row=$result->fetchArray($mode = SQLITE3_NUM))
	{	   	   
		$obj				= new stdClass;
		$obj->id			= $row[0];
		$obj->csv_row		= $row[1];
		$predict_images[] 	= $obj;
	}
	$ret							= new stdClass;	
	$ret->predict					= $predict_images ;

	header('Content-type: application/json');
	echo json_encode($ret);
?>