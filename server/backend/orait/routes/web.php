<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::any('/api/login', 'UserController@login');
Route::any('/api/logout', 'UserController@logout');
Route::any('/api/userInfo', 'UserController@userInfo');

Route::any('/api/data', 'DataController@data');
Route::any('/api/dataDescription', 'DataController@dataDescription');
Route::any('/api/upload', 'DataController@upload');
Route::any('/api/timeChartData', 'DataController@timeChartData');
Route::any('/api/kChartData', 'DataController@kChartData');
Route::any('/api/kChartData', 'DataController@kChartData');
Route::any('/api/accurayChartData', 'DataController@accurayChartData');
Route::any('/api/errorChartData', 'DataController@errorChartData');
Route::any('/api/exportPredictData', 'DataController@exportPredictData');

Route::any('/api/executePredict', 'DrivenController@executePredict');