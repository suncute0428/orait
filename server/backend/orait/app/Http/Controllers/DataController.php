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

        if ($file === null) {
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

                if (!array_key_exists($type, CN_PREDICT_NAME_TO_ID)) {
                    return json_encode(CODE::WRONG_DATA_TYPE);
                }

                $tmpRes = array();
                $tmpRes['date'] = $rowArray['日期']->format('Y-m-d');
                $tmpRes['region'] = $rowArray['地区'];
                $tmpRes['type'] = CN_PREDICT_NAME_TO_ID[$rowArray['数据类型']];

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

        $minDate = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('type', '=', 4)
            ->min('date');

        $ret = CODE::OK;
        if ($maxDate === null) {
            $ret['data']['dataSource'] = array();
            $ret['data']['stastics'] = array();
            $ret['data']['description'] = "";
            return json_encode($ret);
        }

        $before30DateOfMax = (new DateTime($maxDate))->modify("-30 day")->format("Y-m-d");

        $startDate = $before30DateOfMax < $minDate ? $minDate : $before30DateOfMax;

        $sqlResult = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $maxDate)
            ->get();


        $dataSource = array();
        $formatData = array();
        foreach ($sqlResult as $value) {
            $formatData[$value->date][$value->type] = json_decode($value->data, true);
        }

        $oneMonthDateArray = array();

        $interval = new DateInterval('P1D');
        $period = new DatePeriod(new DateTime($startDate), $interval, (new DateTime($maxDate))->modify("+1 day"));
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
                    if (null === $maxValue || $maxValue < $point) {
                        $maxValue = $timePoint;
                    }

                    if (null === $befor) {
                        $befor = $timePoint;
                    } else {
                        $decreValue = $timePoint - $befor;
                        if ($decreValue < 0) {
                            if ($maxDecreValue === null || $maxDecreValue > $decreValue) {
                                $maxDecreValue = $decreValue;
                            }
                        }
                    }

                    if (null === $after) {
                        $after = $timePoint;
                    } else {
                        $increValue = $after - $timePoint;
                        if ($increValue > 0) {
                            if ($maxIncreValue === null || $maxIncreValue < $increValue) {
                                $maxIncreValue = $increValue;
                            }
                        }
                    }
                }
            }
            $tmpStastics['maxValue'] = $maxValue;
            $tmpStastics['maxIncreValue'] = $maxIncreValue;
            $tmpStastics['maxDecreValue'] = $maxDecreValue;
            $stastics[ID_TO_ENG_PREDICT_NAME[$i]] = $tmpStastics;
        }

        $ret['data']['dataSource'] = $dataSource;
        $ret['data']['stastics'] = $stastics;
        $ret['data']['description'] = '深度学习热火朝天，深度工业革命～';
        return $ret;

    }


    public function kChartData(Request $request) {

        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }
        $username = Session::get('username');

        $input = $request->all();
        if (!isset($input['regionId']) || !isset($input['predictType']) || !isset($input['timeType'])) {
            return json_encode(CODE::LACK_PARAM);
        }

        $regionId = $input['regionId'];
        $predictType = $input['predictType'];
        $kchartType = $input['timeType'];

        if ($kchartType != 1 && $kchartType != 2 && $kchartType != 3) {
            return CODE::WRONG_KCHART_TYPE;
        }

        $table = CommonController::predictTypeToTable($predictType);

        $maxDate = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('type', '=', 4)
            ->max('date');

        $minDate = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('type', '=', 4)
            ->min('date');


        if ($kchartType == 1 || $kchartType == 2) { //日K, 周K
            $before1YearDate = (new DateTime($maxDate))->modify("-1 year")->format("Y-m-d");
            $startDate = $before1YearDate < $minDate ? $minDate : $before1YearDate;
        } else { //月K
            $startDate = $minDate;
        }

        if ($kchartType == 1 || $kchartType == 2) {
             $sqlResult = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $maxDate)
            ->get();
        } else {
             $sqlResult = DB::table($table)->where('username', '=', $username)
            ->where('regionId', '=', $regionId)
            ->where('date', '<=', $maxDate)
            ->get();
        }

        $ret = CODE::OK;
        //日K
        $dataSource = $this->generateKChart($sqlResult, $kchartType, $startDate, $maxDate);

        $ret['data']['dataSource'] = $dataSource;
        return json_encode($ret);
    }



    /**
        generate k chart data
    */
    private function generateKChart($data, $kchartType, $startDate, $maxDate) {

        $dataSource = array();
        $formatData = array();
        foreach ($data as $value) {
            $formatData[$value->date][$value->type] = json_decode($value->data, true);
        }

        if ($kchartType == 1) {
            $interval = new DateInterval('P1D');
            $period = new DatePeriod(new DateTime($startDate), $interval, (new DateTime($maxDate))->modify("+1 day"));
        } else if ($kchartType == 2) {
            $interval = new DateInterval('P1W'); 
            //往前推，取最近的周一
            $w = date('w', strtotime($startDate));
            $newStartDate = date('Y-m-d',strtotime("$startDate -".($w ? $w - 1 : 6).' days'));
            $period = new DatePeriod(new DateTime($newStartDate), $interval, (new DateTime($maxDate))->modify("+1 day"));
        } else if ($kchartType == 3) {
            $interval = new DateInterval('P1Y'); 
            //往前推，取最近的1年
            $latestYear = (new DateTime($startDate))->format("Y");
            $newStartDate = $latestYear . '-01-01';
            $period = new DatePeriod(new DateTime($newStartDate), $interval, (new DateTime($maxDate))->modify("+1 day"));
        }

        foreach ($period as $dateTime) {
            $outDate = $dateTime->format('Y-m-d');

            $realValueMin = null; // 实际负荷最小值
            $realValueMax = null;  // 实际负荷最大值
            $realValueAvg = null;  // 实际负荷平均值
            $originPredictMin = null;  // 原始预测最小值
            $originPredictMax = null;  // 原始预测最大值
            $originPredictAvg = null;  // 原始预测平均值
            $originPredictErr = null;    // 原始预测误差值
            $humanPredictMin = null;  // 人工预测最小值
            $humanPredictMax = null;  // 人工预测最大值
            $humanPredictAvg = null;  // 人工预测平均值
            $humanPredictErr = null;  // 人工预测误差值
            $oraitPredictMin = null;  // 橙智预测最小值
            $oraitPredictMax = null;  // 橙智预测最大值
            $oraitPredictAvg = null;  // 橙智预测平均值
            $oraitPredictErr = null;  // 橙智预测误差值

            $effectRealPointNums = 0;
            $effectOriginPointNums = 0;
            $effectHumanPointNums = 0;
            $effectOraitPointNums = 0;

            //获取每种K线图需要统计的日期区间
            $sd = $outDate;
            if ($kchartType == 1) {
                $ed = $sd;
            } else if ($kchartType == 2) {
                $ed = (new DateTime($sd))->modify("+7 day")->format("Y-m-d");
            } else if ($kchartType == 3) {
                $ed = (new DateTime($sd))->modify("+1 year")->format("Y-m-d");
            }

            $innerInterval = new DateInterval('P1D');
            $innertPeriod = new DatePeriod(new DateTime($sd), $innerInterval, (new DateTime($ed))->modify("+1 day"));

            foreach ($innertPeriod as $innerDateTime) {
                $date = $innerDateTime->format("Y-m-d");
                foreach ($this->title as $titleOriginal => $titleShow) {
                //实际值
                if (array_key_exists($date, $formatData) && array_key_exists("1", $formatData[$date]) && array_key_exists($titleOriginal, $formatData[$date]["1"])) {
                    $effectRealPointNums++;
                    $realTimePoint = $formatData[$date]["1"][$titleOriginal];
                    if ($realValueMax === null) {
                        $realValueMax = $realTimePoint;
                    } else if ($realValueMax < $realTimePoint){
                        $realValueMax = $realTimePoint;
                    }

                    if ($realValueMin === null) {
                        $realValueMin = $realTimePoint;
                    } else if ($realValueMin > $realTimePoint) {
                        $realValueMin = $realTimePoint;
                    }

                    if ($realValueAvg === null) {
                        $realValueAvg = $realTimePoint;
                    } else {
                        $realValueAvg += $realTimePoint;
                    }
                }

                //原始预测
                if (array_key_exists($date, $formatData) && array_key_exists("2", $formatData[$date]) && array_key_exists($titleOriginal, $formatData[$date]["2"])) {
                    $effectOriginPointNums++;
                    $originTimePoint = $formatData[$date]["2"][$titleOriginal];
                    if ($originPredictMax === null) {
                        $originPredictMax = $originTimePoint;
                    } else if ($realValueMax < $originTimePoint){
                        $originPredictMax = $originTimePoint;
                    }

                    if ($originPredictMin === null) {
                        $originPredictMin = $originTimePoint;
                    } else if ($originPredictMin > $originTimePoint) {
                        $originPredictMin = $originTimePoint;
                    }

                    if ($originPredictAvg === null) {
                        $originPredictAvg = $originTimePoint;
                    } else {
                        $originPredictAvg += $originTimePoint;
                    }

                    if (array_key_exists($date, $formatData) && array_key_exists("1", $formatData[$date]) && array_key_exists($titleOriginal, $formatData[$date]["1"])) { //存在实际值
                        if ($originPredictErr === null) {
                            $originPredictErr = pow(($originTimePoint - $realTimePoint)/$originTimePoint, 2);
                        } else {
                            $originPredictErr += pow(($originTimePoint - $realTimePoint)/$originTimePoint, 2);
                        }
                    }
                }

                //人工预测
                if (array_key_exists($date, $formatData) && array_key_exists("3", $formatData[$date]) && array_key_exists($titleOriginal, $formatData[$date]["3"])) {
                    $effectHumanPointNums++;
                    $humanTimePoint = $formatData[$date]["3"][$titleOriginal];
                    if ($humanPredictMax === null) {
                        $humanPredictMax = $humanTimePoint;
                    } else if ($humanPredictMax < $humanTimePoint){
                        $humanPredictMax = $humanTimePoint;
                    }

                    if ($humanPredictMin === null) {
                        $humanPredictMin = $humanTimePoint;
                    } else if ($humanPredictMin > $humanTimePoint) {
                        $humanPredictMin = $humanTimePoint;
                    }

                    if ($humanPredictAvg === null) {
                        $humanPredictAvg = $humanTimePoint;
                    } else {
                        $humanPredictAvg += $humanTimePoint;
                    }

                    if (array_key_exists($date, $formatData) && array_key_exists("1", $formatData[$date]) && array_key_exists($titleOriginal, $formatData[$date]["1"])) { //存在实际值

                        if ($humanPredictErr === null) {
                            $humanPredictErr = pow(($humanTimePoint - $realTimePoint)/$humanTimePoint, 2);
                        } else {
                            $humanPredictErr += pow(($humanTimePoint - $realTimePoint)/$humanTimePoint, 2);
                        }
                    }
                }

                //橙智预测
                if (array_key_exists($date, $formatData) && array_key_exists("4", $formatData[$date]) && array_key_exists($titleOriginal, $formatData[$date]["4"])) {
                    $effectOraitPointNums++;
                    $oraitTimePoint = $formatData[$date]["4"][$titleOriginal];
                    if ($oraitPredictMax === null) {
                        $oraitPredictMax = $oraitTimePoint;
                    } else if ($oraitPredictMax < $oraitTimePoint){
                        $oraitPredictMax = $oraitTimePoint;
                    }

                    if ($oraitPredictMin === null) {
                        $oraitPredictMin = $oraitTimePoint;
                    } else if ($oraitPredictMin > $oraitTimePoint) {
                        $oraitPredictMin = $oraitTimePoint;
                    }

                    if ($oraitPredictAvg === null) {
                        $oraitPredictAvg = $oraitTimePoint;
                    } else {
                        $oraitPredictAvg += $oraitTimePoint;
                    }

                    if (array_key_exists($date, $formatData) && array_key_exists("1", $formatData[$date]) && array_key_exists($titleOriginal, $formatData[$date]["1"])) { //存在实际值

                        if ($oraitPredictErr === null) {
                            $oraitPredictErr = pow(($oraitTimePoint - $realTimePoint)/$oraitTimePoint, 2);
                        } else {
                            $oraitPredictErr += pow(($oraitTimePoint - $realTimePoint)/$oraitTimePoint, 2);
                        }
                    }
                }
            }
            }

            $dataSource[] = [
                    "date" => $outDate,
                    "realValueMin" => $realValueMin, // 实际负荷最小值
                    "realValueMax" => $realValueMax,  // 实际负荷最大值
                    "realValueAvg" => $realValueAvg === null ? null:  $realValueAvg / $effectRealPointNums,   // 实际负荷平均值
                    "originPredictMin" => $originPredictMin,  // 原始预测最小值
                    "originPredictMax" => $originPredictMax,  // 原始预测最大值
                    "originPredictAvg" => $originPredictAvg === null ? null : ($originPredictAvg/ $effectOriginPointNums),  // 原始预测平均值
                    "originPredictErr" => $originPredictErr === null ? null : pow($originPredictErr / ($effectOriginPointNums > $effectRealPointNums ? $effectRealPointNums : $effectOriginPointNums), 2),    // 原始预测误差值
                    "humanPredictMin" => $humanPredictMin,  // 人工预测最小值
                    "humanPredictMax" => $humanPredictMax,  // 人工预测最大值
                    "humanPredictAvg" => $humanPredictAvg === null ? null : ($humanPredictAvg / $effectHumanPointNums),  // 人工预测平均值
                    "humanPredictErr" => $humanPredictErr === null ? null : pow($humanPredictErr / ($effectHumanPointNums > $effectRealPointNums ? $effectRealPointNums : $effectHumanPointNums), 2),  // 人工预测误差值
                    "oraitPredictMin" => $oraitPredictMin,  // 橙智预测最小值
                    "oraitPredictMax" => $oraitPredictMax,  // 橙智预测最大值
                    "oraitPredictAvg" => $oraitPredictAvg === null ? null : ($oraitPredictAvg / $effectOraitPointNums),  // 橙智预测平均值
                    "oraitPredictErr" => $oraitPredictErr === null ? null : pow($oraitPredictErr / ($effectOraitPointNums > $effectRealPointNums ? $effectRealPointNums : $effectOraitPointNums), 2)  // 橙智预测误差值
                ];
        }

        return $dataSource;
    }


    public function accurayChartData(Request $request) {
        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }
        $username = Session::get('username');

        $input = $request->all();
        if (!isset($input['regionId']) || !isset($input['predictType']) || !isset($input['timeType'])) {
            return json_encode(CODE::LACK_PARAM);
        }

        $regionId = $input['regionId'];
        $predictType = $input['predictType'];
        $timeType = $input['timeType'];

        //driven algorithm module
        $ret = CODE::OK;
        //mock data
        $ret['data']['originPredict'] = 0.944;
        $ret['data']['humanPredict'] = 0.974;
        $ret['data']['oraitPredict'] = 0.989;
        return json_encode($ret);
    }


    public function errorChartData(Request $request) {
        if (!CommonController::auth($request)) {
            return json_encode(CODE::NOT_LOGIN);
        }
        $username = Session::get('username');

        $input = $request->all();
        if (!isset($input['regionId']) || !isset($input['predictType']) || !isset($input['date'])) {
            return json_encode(CODE::LACK_PARAM);
        }

        $regionId = $input['regionId'];
        $predictType = $input['predictType'];
        $date = $input['date'];

        //driven algorithm module
        $ret = CODE::OK;

        //mock data
        $dataSource = array();
        foreach ($this->title as $titleOriginal => $titleShow) {
            $tmpData = array();
            $tmpData['time'] = $titleShow;
            $tmpData['weather'] = 0.015;
            $tmpData['change'] = 0.018;
            $tmpData['model'] = 0.027;
            $dataSource[] = $tmpData;
        }

        $stastics = array();
        $tmpStastics = array();
        $tmpStastics['startTime'] = "00:30";
        $tmpStastics['endTime'] = "01:00";
        $tmpStastics['startIndex'] = 2;
        $tmpStastics['endIndex'] = 4;
        $tmpStastics['weather'] = 0.015;
        $tmpStastics['change'] = 0.018;
        $tmpStastics['model'] = 0.027;
        $stastics[] = $tmpStastics;
        
        $ret['data']['dataSource'] = $dataSource;
        $ret['data']['stastics'] = $stastics;
        return json_encode($ret);
    }


    public function exportPredictData(Request $request) {
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


        $regionName = DB::table("region")->select('name')->where('id', '=', $regionId)->get()[0]->name;
        //todo
    }
}
