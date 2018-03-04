<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\CODE;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\CommonController;


/**
	user manager
 */
class UserController extends Controller
{
	public function login(Request $request) {
		$input = $request->all();

		if (!isset($input['username']) || !isset($input['password'])) {
			return json_encode(CODE::LACK_PARAM);
		}

		if (empty($input['username'])) {
            return json_encode(CODE::EMPYY_USER);
        }

        $password = DB::table('user')->where('username', '=', $input['username'])->get();

		if (count($password) == 0) {
		    return json_encode(CODE::USER_NOT_EXIST);
        }

        if (md5($input['password']) == $password[0]->password) {
            Session::put("username", $input['username']);
            Session::save();
            return json_encode(CODE::OK);
        } else {
		    return json_encode(CODE::WRONG_PASSWD);
        }
	}

	public function logout(Request $request) {
        Session::flush();
    }

    public function userInfo(Request $request) {

        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }

	    $username = Session::get('username');

        $predictType = DB::table('user')->where('username', '=', $username)->get();

        $regions = DB::table('user_regions')->leftJoin('region', 'user_regions.regionId', '=', 'region.id')
            ->select('name', 'regionId as value')->get();

	    $ret = CODE::OK;
        $ret['data']['username'] = $username;
        $ret['data']['predictType'] = count($predictType) == 0 ? 0 : $predictType[0]->predictType;
        $ret['data']['regions'] = $regions;

	    return json_encode($ret);
    }
}
