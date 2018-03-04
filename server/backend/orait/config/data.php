<?php

namespace App\Http\Controllers;

const CN_PREDICT_NAME_TO_ID = ['实际负荷' => 1
			,'原始预测' => 2
			,'人工预测' => 3
			,'橙智预测' => 4
			,];

const ID_TO_ENG_PREDICT_NAME = ['1' => "realValue"
    		,'2' => "originPredict"
        	,'3' => "humanPredict"
        	,'4' => "oraitPredict"
    	];




const KCHART_VALUE_COLLECTION = ['realValueMin' => null
			,'realValueMax' => null
			,'realValueAvg' => null
			,'originPredictMin' => null
			,'originPredictMax' => null
			,'originPredictAvg' => null
			,'originPredictErr' => null
			,'humanPredictMin' => null
			,'humanPredictMax' => null
			,'humanPredictAvg' => null
			,'humanPredictErr' => null
			,'oraitPredictMin' => null
			,'oraitPredictMax' => null
			,'oraitPredictAvg' => null
			,'oraitPredictErr' => null
		];