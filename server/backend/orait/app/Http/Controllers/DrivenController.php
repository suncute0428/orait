<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\Session;

class DrivenController extends Controller
{
    public function executePredict(Request $request) {

    	if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }

	    $username = Session::get('username');

	    $input = $request->all();
        if (!isset($input['predictType']) || !isset($input['date'])) {
            return json_encode(CODE::LACK_PARAM);
        }

	    //mock
        return json_encode(CODE::OK);

    }
}
