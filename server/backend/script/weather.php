<?php

const PATH = '../../../data/weather/';

$mysqli = new mysqli('rm-bp1l3fz1m9b8uk4m65o.mysql.rds.aliyuncs.com', 'root', 'Czkj779656332', 'orait');
if ($mysqli->connect_errno) {
	die('can not connect to mysql:\n' . $mysqli->connect_errno);
}

if (!$mysqli->select_db('orait')) {
	die('can not connect to mysql db[orait]:\n' . $mysqli->error);
}

//获取已经入库更新的天气文件名称
$result = $mysqli->query('select * from weather_file');
$weatherFilesAlreadyInDB = array();
foreach ($result->fetch_all(MYSQLI_ASSOC) as $value) {
	$weatherFilesAlreadyInDB[$value['filename']] = $value['updateTime'];
}

//获取PATH下面的天气文件
$nowWeatherFiles = getFile(PATH);

//compare and parse file to db
foreach ($nowWeatherFiles as $filename => $fileLastModifyTime) {
	if (!isset($weatherFilesAlreadyInDB[$filename]) || $weatherFilesAlreadyInDB[$filename] < $fileLastModifyTime) { //parse file
		$file = fopen(PATH . $filename, 'r') or contiune;
		$firstLine = substr(fgets($file), 2);
		$latiAndLong = array_values(array_filter(explode("\t", $firstLine)));

		//skip two lines
		fgets($file);fgets($file);

		//read title
		$originalTitles = array_filter(explode("\t", fgets($file)));
		$titles = array_values($originalTitles);

		while(!feof($file)) { //读取每一行
  			$line = array_values(array_filter(explode("\t", fgets($file))));

  			if (count($line) > 1) {
  			
  				$latitude = $latiAndLong[(substr($line[0], 1) - 1) * 2];
  				$longtitude = $latiAndLong[(substr($line[0], 1) - 1) * 2 + 1];
  				$time = $line[1];

  				$data = array();
  				for ($i = 2; $i < count($line); $i++) {
  					$data[$titles[$i - 2]] = $line[$i];
  				}

  				$status = $mysqli->query("insert into weather_data(latitude,longtitude,time,data) values($latitude, $longtitude, \"$time\",\"" . $mysqli->real_escape_string(json_encode($data)) . "\") on duplicate key update data=\"" . $mysqli->real_escape_string(json_encode($data)) . "\" ,updateTime=now()" );
  				if (!$status) {
  					echo "WARNING: insert data failed, " . $mysqli->error . "\n";
  				}
  			}
		}

		$fileStore = $mysqli->query("insert into weather_file(filename,updateTime) values(\"$filename\", \"$fileLastModifyTime\")");
		if (!$fileStore) {
  			echo "WARNING: save file status failed, " . $mysqli->error . "\n";
		}

		fclose($file);
	} else {
		echo "INFO: file has no update, skip it [" . $filename . "]\n";
	}
}

//release
$mysqli->close();


//获取文件列表
function getFile($dir) {
    $fileArray = array();
    if (false != ($handle = opendir($dir))) {
        while (false !== ($file = readdir($handle))) {
            //去掉"“.”、“..”以及带“.xxx”后缀的文件
            if ($file != "." && $file != ".." && strpos($file, ".")) {
            	$path = $dir . $file;
            	$time = date("Y-m-d H:i:s", filemtime($path));
                $fileArray[$file] = $time;
            }
        }
        //关闭句柄
        closedir($handle);
    }
    return $fileArray;
}