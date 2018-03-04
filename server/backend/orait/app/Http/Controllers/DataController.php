<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\CODE;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;
use Storage;
use Excel;
use DateTime;
use DateInterval;
use DatePeriod;
use App\Http\Controllers\CommonController;
use Illuminate\Support\Facades\Session;

class DataController extends Controller
{

    const dataType = ['实际负荷' => 1
            ,'原始预测' => 2
            ,'人工预测' => 3
            ,'橙智预测' => 4
            ];

    private $innerType = ['1' => "realValue"
        ,'2' => "originPredict"
        ,'3' => "humanPredict"
        ,'4' => "oraitPredict"
    ];

    protected $title = array();

    public function __construct() {
        for ($i = 0; $i < 96; $i++) {
            $this->title[date('Hi', strtotime("+15 minute", 56700 + 900 * $i))] = date('H:i', strtotime("+15 minute", 56700 + 900 * $i));
        }
    }

    public function upload(Request $request) {

        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }

        $username = Session::get('username');

        $input = $request->all();

        if (!isset($input['predictType'])) {
            return json_encode(CODE::LACK_PARAM);
        }
        $predictType = $input['predictType'];

        $file = $request->file('source');

        if ($file == null) {
            return json_encode(CODE::LACK_PARAM);
        }

        if ($file->isValid()) {
            $extension = $file->getClientOriginalExtension();
            $type = $file->getClientMimeType();
            $realPath = $file->getRealPath();
            $filename = $username.'_'.date('YmdHis').'_'.uniqid().'.'.$extension;
            $success = Storage::disk('uploads')->put($filename, file_get_contents($realPath));

            if (!$success) {
                return json_encode(CODE::FILE_SAVED_FAILED);
            }

            $reader = Excel::load($realPath, function($reader) {});

            // $sheetCount = $reader->getSheetCount();
            // $sheetNames = $reader->getSheetNames();
            // $heading = $reader->all()->getHeading();
            // $title = $reader->all()->getTitle();

            $rowCollection = $reader->all();
            $cellCollectionArray = $rowCollection->all();
            //first check and insert regin info
            $data = array();
            foreach ($cellCollectionArray as $id => $cell) {
                $rowArray = $cell->all();
                $region = $rowArray['地区'];
                $type = $rowArray['数据类型'];

                $regionCheck = DB::select('select * from region where name=?', [$region]);
                if (empty($regionCheck)) { //add region
                    DB::insert('insert ignore into region(name) value(?)', [$region]);
                    $idRes = DB::select('select * from region where name=?', [$region]);
                    DB::insert('insert ignore into user_regions(username, regionId) value(?,?)', [$username, $idRes[0]->id]);
                }

                if (!array_key_exists($type, dataType)) {
                    return json_encode(CODE::WRONG_DATA_TYPE);
                }

                $tmpRes = array();
                $tmpRes['date'] = $rowArray['日期']->format('Y-m-d');
                $tmpRes['region'] = $rowArray['地区'];
                $tmpRes['type'] = dataType[$rowArray['数据类型']];

                //check and format data
                $points = array();
                foreach ($this->title as $point => $value) {
                    if (isset($rowArray[$point])) {
                        if (is_numeric($rowArray[$point])) {
                            $points[$point] = (float) $rowArray[$point];
                        } else {
                            return CODE::DATA_FORMAT_ERROR;                            
                        }
                    } else {
                        $points[$point] = null;
                    }
                }
                $tmpRes['data'] = $points;
                $data[] = $tmpRes;
            }

            //get regionId
            $regionResult = DB::select('select id, name from region');
            $regionInfo = [];
            foreach ($regionResult as $value) {
                $regionInfo[$value->name] = $value->id;
            }

            //insert data
            $table = CommonController::predictTypeToTable($predictType);
            foreach ($data as $row) {
                DB::insert('insert into ' . $table . '(username, date, regionId, type, data) values(?,?,?,?,?) ON DUPLICATE KEY UPDATE data=?', [$username, $row['date'], $regionInfo[$row['region']], $row['type'], json_encode($row['data']), json_encode($row['data'])]);
            }

            return CODE::OK;
        } else {
            return json_encode(CODE::UPLOAD_FAILED);
        }
    }

    public function data(Request $request) {

        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }

        $username = Session::get('username');

        $input = $request->all();
        if (!isset($input['regionId']) || !isset($input['startDate']) || !isset($input['predictType'])) {
            return json_encode(CODE::LACK_PARAM);
        }

        $regionId = $input['regionId'];
        $startDate = $input['startDate'];
        $predictType = $input['predictType'];
        $endDate = empty($input['endDate']) ? date("Y-m-d") : $input['endDate'];
        $table = CommonController::predictTypeToTable($predictType); 

        $sqlResult = DB::table($table)->where('username', '=', $username)
                                    ->where('regionId', '=', $regionId)
                                    ->where('type', '<=', 3)
                                    ->where('type', '>=', 1)
                                    ->where('date', '>=', $startDate)
                                    ->where('date', '<=', $endDate)
                                    ->get();
        
        $ret = CODE::OK;
        $data = array();
        foreach ($sqlResult as $value) {
            $row = json_decode($value->data, true);
            $row['date'] = $value->date;
            $row['type'] = $value->type;
            $row['emptyCount'] = $value->emptyCount;
            $row['zeroCount'] = $value->zeroCount;
            $row['skipCount'] = $value->skipCount;
            $row['invariantCount'] = $value->invariantCount;
            $data[] = $row;
        }

        $ret['data']['dataSource'] = $data;
        $column = array();
        $column[] = ['dataIndex' => 'date', 'title' => '日期'];
        $column[] = ['dataIndex' => 'type', 'title' => '数据类型'];
        
        foreach ($this->title as $point => $value) {
            $column[] = ['dataIndex' => $point, 'title' => $value];
        }

        $ret['data']['columns'] = $column;
        return json_encode($ret);
    }

    public function dataDescription(Request $request) {
        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }

        $username = Session::get('username');

        $input = $request->all();
        if (!isset($input['regionId']) || !isset($input['startDate'])) {
            return json_encode(CODE::LACK_PARAM);
        }

        //maybe lack predictType
        $regionId = $input['regionId'];
        $startDate = $input['startDate'];
        $endDate = empty($input['endDate']) ? date("Y-m-d") : $input['endDate'];
        //mock, driven python algorithm module
        $ret = CODE::OK;
        $ret['data']['message'] = '深度学习热火朝天，深度工业革命～';
        return json_encode($ret);
    }


    public function timeChartData(Request $request) {

        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }
        $username = Session::get('username');

        $input = $request->all();
        if (!isset($input['regionId']) || !isset($input['predictType'])) {
            return json_encode(CODE::LACK_PARAM);
        }

        $regionId = $input['regionId'];
        $predictType = $input['predictType'];

        $table = CommonController::predictTypeToTable($predictType);

        $maxDate = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('type', '=', 4)
            ->max('date');

        $ret = CODE::OK;
        if ($maxDate == null) {
            $ret['data']['dataSource'] = array();
            $ret['data']['stastics'] = array();
            $ret['data']['description'] = "";
            return json_encode($ret);
        }

        $startDate = (new DateTime($maxDate))->modify("-30 day");

        $sqlResult = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('date', '>=', $startDate->format("Y-m-d"))
            ->where('date', '<=', $maxDate)
            ->get();


        $dataSource = array();
        $formatData = array();
        foreach ($sqlResult as $value) {
            $formatData[$value->date][$value->type] = json_decode($value->data, true);
        }

        $oneMonthDateArray = array();

        $interval = new DateInterval('P1D');
        $period = new DatePeriod($startDate, $interval, (new DateTime($maxDate))->modify("+1 day"));
        foreach($period as $dateTime) {
//        foreach ($formatData as $date => $eachDateData) {
            $date = $dateTime->format('Y-m-d');
            foreach ($this->title as $titleOriginal => $titleShow) {
                $point = array();
                $point['date'] = $date;
                $point['time'] = $titleShow;
//                $point['realValue'] = array_key_exists("1", $eachDateData) ? (array_key_exists($titleOriginal, $eachDateData["1"]) ? $eachDateData["1"][$titleOriginal] : null) : null;
//                $point['originPredict'] = array_key_exists("2", $eachDateData) ? (array_key_exists($titleOriginal, $eachDateData["2"]) ? $eachDateData["2"][$titleOriginal] : null) : null;
//                $point['humanPredict'] = array_key_exists("3", $eachDateData) ? (array_key_exists($titleOriginal, $eachDateData["3"]) ? $eachDateData["3"][$titleOriginal] : null) : null;
//                $point['oraitPredict'] = array_key_exists("4", $eachDateData) ? (array_key_exists($titleOriginal, $eachDateData["4"]) ? $eachDateData["4"][$titleOriginal] : null) : null;
                $point['realValue'] = array_key_exists($date, $formatData) ? (array_key_exists("1", $formatData[$date]) ? (array_key_exists($titleOriginal, $formatData[$date]['1']) ? $formatData[$date]["1"][$titleOriginal] : null) : null) : null;
                $point['originPredict'] = array_key_exists($date, $formatData) ? (array_key_exists("2", $formatData[$date]) ? (array_key_exists($titleOriginal, $formatData[$date]['2']) ? $formatData[$date]["2"][$titleOriginal] : null) : null) : null;
                $point['humanPredict'] = array_key_exists($date, $formatData) ? (array_key_exists("3", $formatData[$date]) ? (array_key_exists($titleOriginal, $formatData[$date]['3']) ? $formatData[$date]["3"][$titleOriginal] : null) : null) : null;
                $point['oraitPredict'] = array_key_exists($date, $formatData) ? (array_key_exists("4", $formatData[$date]) ? (array_key_exists($titleOriginal, $formatData[$date]['4']) ? $formatData[$date]["4"][$titleOriginal] : null) : null) : null;

                $dataSource[] = $point;
            }
        }

        $latestDateData = $formatData[$maxDate];
        $stastics = array();
        for ($i = 1; $i <= 4; $i++) {
            $tmpStastics = array();
            $maxValue = null;
            $maxIncreValue = null;
            $maxDecreValue = null;

            $befor = null;
            $after = null;
            if (array_key_exists($i, $latestDateData)) {
                foreach ($this->title as $titleOriginal => $titleShow) {
                    $timePoint = $latestDateData[$i][$titleOriginal];
                    if (null == $maxValue || $maxValue < $point) {
                        $maxValue = $timePoint;
                    }

                    if (null == $befor) {
                        $befor = $timePoint;
                    } else {
                        $decreValue = $timePoint - $befor;
                        if ($decreValue < 0) {
                            if ($maxDecreValue == null || $maxDecreValue > $decreValue) {
                                $maxDecreValue = $decreValue;
                            }
                        }
                    }

                    if (null == $after) {
                        $after = $timePoint;
                    } else {
                        $increValue = $after - $timePoint;
                        if ($increValue > 0) {
                            if ($maxIncreValue == null || $maxIncreValue < $increValue) {
                                $maxIncreValue = $increValue;
                            }
                        }
                    }
                }
            }
            $tmpStastics['maxValue'] = $maxValue;
            $tmpStastics['maxIncreValue'] = $maxIncreValue;
            $tmpStastics['maxDecreValue'] = $maxDecreValue;
            $stastics[$this->innerType[$i]] = $tmpStastics;
        }

        $ret['data']['dataSource'] = $dataSource;
        $ret['data']['stastics'] = $stastics;
        $ret['data']['description'] = '深度学习热火朝天，深度工业革命～';
        return $ret;

    }


    public function kchartData(Request $request) {

        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }
        $username = Session::get('username');

        $input = $request->all();
        if (!isset($input['regionId']) || !isset($input['predictType']) || !isset($input['kchartType'])) {
            return json_encode(CODE::LACK_PARAM);
        }

        $regionId = $input['regionId'];
        $predictType = $input['predictType'];
        $kchartType = $input['kchartType'];

        $table = CommonController::predictTypeToTable($predictType);

        $maxDate = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('type', '=', 4)
            ->max('date');

        if ($kchartType == 1) {
            
        }
        $startDate = (new DateTime($maxDate))->modify("-30 day");



    }

}
