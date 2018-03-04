<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CommonController extends Controller
{
    public static function auth(Request $request) {
        if ($request->path() != 'api/login' && $request->path() != 'api/logout' && null == Session::get('username')) {
            return false;
        }
        return true;
    }

    public static function predictTypeToTable(int $predictType) {
    	switch ($predictType) {
    		case 1:
    			return "short_load_predict";
    		case 2:
    			return "ultra_short_load_predict";
    		case 3:
    			return "electricity_predict";
    		default:
    			throw Exception("wrong predict type");
    	}
    }
}
