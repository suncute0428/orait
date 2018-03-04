<?php

namespace App\Http\Controllers;

class CODE {

	const NOT_LOGIN = ['status' => 1001, 'message' => 'user is not in login status'];
	const OK = ['status' => 0, 'message' => 'success'];
	const LACK_PARAM = ['status' => -1, 'message' => 'lack of params'];
	const USER_NOT_EXIST = ['status' => -2, 'message' => 'user is not exist'];
	const WRONG_PASSWD = ['status' => -3, 'message' => 'wrong passwd'];
	const EMPYY_USER = ['status' => -4, 'message' => 'username can not be empty'];
	const UPLOAD_FAILED = ['status' => -5, 'message' => 'file upload failed'];
	const FILE_SAVED_FAILED = ['status' => -6, 'message' => 'save upload file failed'];
	const WRONG_KCHART_TYPE = ['status' => -7, 'message' => 'wrong kchart type, must be 1|2|3'];


	const WRONG_DATA_TYPE = ['status' => -101, 'message' => 'wrong data type, please check'];
	const DATA_FORMAT_ERROR = ['status' => -102, 'message' => 'data format must be number'];
}